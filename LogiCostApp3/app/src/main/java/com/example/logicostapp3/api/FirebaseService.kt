package com.example.logicostapp3.api

import com.example.logicostapp3.model.*
import com.google.firebase.auth.FirebaseAuth
import com.google.firebase.auth.FirebaseAuthInvalidCredentialsException
import com.google.firebase.auth.FirebaseAuthInvalidUserException
import com.google.firebase.auth.FirebaseAuthUserCollisionException
import com.google.firebase.auth.FirebaseAuthWeakPasswordException
import com.google.firebase.auth.FirebaseUser
import com.google.firebase.auth.GoogleAuthProvider
import com.google.firebase.firestore.DocumentSnapshot
import com.google.firebase.firestore.FieldValue
import com.google.firebase.firestore.FirebaseFirestore
import kotlinx.coroutines.tasks.await
import kotlinx.coroutines.withTimeout
import retrofit2.Response
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import kotlin.random.Random

/**
 * Firebase implementation of the LogiCost data API.
 *
 * Authentication uses Firebase Auth (email/password and Google).
 * Business data is stored in Cloud Firestore. Every record carries
 * the companyId of the business that owns it, and the Firestore
 * security rules only allow a user to read or change their own
 * company's records.
 *
 * The screens were written against ApiService, so this class keeps
 * the same functions and response types. The token parameters are
 * ignored because Firebase Auth identifies the signed-in user.
 */
class FirebaseService private constructor() : ApiService {

    companion object {
        val instance: FirebaseService by lazy { FirebaseService() }

        private const val USERS = "users"
        private const val COMPANIES = "companies"
        private const val INVENTORY = "inventory"
        private const val DELIVERIES = "deliveries"
        private const val LISTINGS = "marketplace_listings"
        private const val QUOTES = "marketplace_quotes"

        // Fail with a clear error instead of waiting forever offline.
        private const val TIMEOUT_MS = 20_000L
    }

    private val auth: FirebaseAuth = FirebaseAuth.getInstance()
    private val db: FirebaseFirestore = FirebaseFirestore.getInstance()

    // Cached company of the signed-in user.
    private var cachedUid: String? = null
    private var cachedCompanyId: Int = 0
    private var cachedBusinessName: String = ""


    // Authentication

    override suspend fun login(request: LoginRequest): Response<AuthResponse> =
        try {
            val result = withTimeout(TIMEOUT_MS) {
                auth.signInWithEmailAndPassword(
                    request.email,
                    request.password
                ).await()
            }

            val user = result.user
                ?: return fail(AuthResponse(false, "Login failed. Please try again."))

            Response.success(authResponseFor(user))
        } catch (e: FirebaseAuthInvalidUserException) {
            fail(AuthResponse(false, "No account was found for that email."))
        } catch (e: FirebaseAuthInvalidCredentialsException) {
            fail(AuthResponse(false, "Incorrect email or password."))
        }

    override suspend fun register(request: RegisterRequest): Response<AuthResponse> =
        try {
            val result = withTimeout(TIMEOUT_MS) {
                auth.createUserWithEmailAndPassword(
                    request.email,
                    request.password
                ).await()
            }

            val user = result.user
                ?: return fail(AuthResponse(false, "Registration failed. Please try again."))

            createProfile(
                user = user,
                fullname = request.fullname,
                businessName = request.businessName,
                businessType = request.businessType,
                phone = request.phone,
                province = request.province,
                city = request.city
            )

            Response.success(authResponseFor(user))
        } catch (e: FirebaseAuthUserCollisionException) {
            fail(AuthResponse(false, "An account with that email already exists."))
        } catch (e: FirebaseAuthWeakPasswordException) {
            fail(AuthResponse(false, "Password is too weak. Use at least 6 characters."))
        } catch (e: FirebaseAuthInvalidCredentialsException) {
            fail(AuthResponse(false, "Please enter a valid email address."))
        }

    /**
     * Signs in with a Google ID token (single sign-on).
     * A first-time Google user gets a new company profile.
     */
    suspend fun signInWithGoogle(idToken: String): Response<AuthResponse> {
        val credential = GoogleAuthProvider.getCredential(idToken, null)

        val result = withTimeout(TIMEOUT_MS) {
            auth.signInWithCredential(credential).await()
        }

        val user = result.user
            ?: return fail(AuthResponse(false, "Google sign-in failed. Please try again."))

        val profile = db.collection(USERS).document(user.uid).get().await()

        if (!profile.exists()) {
            val name = user.displayName?.takeIf { it.isNotBlank() } ?: "LogiCost User"

            createProfile(
                user = user,
                fullname = name,
                businessName = "$name's Business",
                businessType = "Manufacturer",
                phone = user.phoneNumber ?: "",
                province = "Gauteng",
                city = "Johannesburg"
            )
        }

        return Response.success(authResponseFor(user))
    }

    fun signOut() {
        auth.signOut()
        cachedUid = null
        cachedCompanyId = 0
        cachedBusinessName = ""
    }

    fun isSignedIn(): Boolean = auth.currentUser != null

    /** True when the user signed in with email and password (not Google). */
    fun usesPassword(): Boolean =
        auth.currentUser?.providerData?.any { it.providerId == "password" } == true


    // Settings: profile

    data class Profile(
        val fullname: String,
        val email: String,
        val businessName: String,
        val businessType: String,
        val phone: String,
        val province: String,
        val city: String
    )

    suspend fun getProfile(): Profile {
        val user = auth.currentUser
            ?: throw IllegalStateException("You are not signed in.")

        val companyId = companyId()

        val profile = db.collection(USERS).document(user.uid).get().await()
        val company = db.collection(COMPANIES).document(companyId.toString()).get().await()

        return Profile(
            fullname = profile.getString("fullname") ?: "",
            email = profile.getString("email") ?: (user.email ?: ""),
            businessName = company.getString("businessName") ?: "",
            businessType = company.getString("businessType") ?: "",
            phone = company.getString("phone") ?: "",
            province = company.getString("province") ?: "",
            city = company.getString("city") ?: ""
        )
    }

    suspend fun updateProfile(profile: Profile) {
        val user = auth.currentUser
            ?: throw IllegalStateException("You are not signed in.")

        val companyId = companyId()
        val batch = db.batch()

        batch.update(
            db.collection(USERS).document(user.uid),
            mapOf("fullname" to profile.fullname)
        )

        batch.update(
            db.collection(COMPANIES).document(companyId.toString()),
            mapOf(
                "businessName" to profile.businessName,
                "phone" to profile.phone,
                "province" to profile.province,
                "city" to profile.city
            )
        )

        withTimeout(TIMEOUT_MS) { batch.commit().await() }

        cachedBusinessName = profile.businessName
    }

    suspend fun sendPasswordReset() {
        val email = auth.currentUser?.email
            ?: throw IllegalStateException("Your account has no email address.")

        withTimeout(TIMEOUT_MS) { auth.sendPasswordResetEmail(email).await() }
    }

    private suspend fun createProfile(
        user: FirebaseUser,
        fullname: String,
        businessName: String,
        businessType: String,
        phone: String,
        province: String,
        city: String
    ) {
        val companyId = newId()
        val now = timestamp()

        val batch = db.batch()

        batch.set(
            db.collection(COMPANIES).document(companyId.toString()),
            mapOf(
                "id" to companyId,
                "businessName" to businessName,
                "businessType" to businessType,
                "phone" to phone,
                "province" to province,
                "city" to city,
                "ownerUid" to user.uid,
                "createdAt" to now
            )
        )

        batch.set(
            db.collection(USERS).document(user.uid),
            mapOf(
                "fullname" to fullname,
                "email" to (user.email ?: ""),
                "role" to "owner",
                "companyId" to companyId,
                "createdAt" to now
            )
        )

        withTimeout(TIMEOUT_MS) { batch.commit().await() }

        cachedUid = user.uid
        cachedCompanyId = companyId
        cachedBusinessName = businessName

        // Give the new business sample records to work with.
        SampleData.seedCompany(db, companyId)
    }

    private suspend fun authResponseFor(user: FirebaseUser): AuthResponse {
        val profile = db.collection(USERS).document(user.uid).get().await()

        return AuthResponse(
            success = true,
            message = "Welcome to LogiCost.",
            user = User(
                id = profile.getLong("companyId")?.toInt() ?: 0,
                fullname = profile.getString("fullname") ?: (user.displayName ?: ""),
                email = profile.getString("email") ?: (user.email ?: ""),
                role = profile.getString("role") ?: "owner",
                createdAt = profile.getString("createdAt") ?: ""
            ),
            token = user.uid
        )
    }

    /** Returns the signed-in user's company id, loading it once. */
    private suspend fun companyId(): Int {
        val user = auth.currentUser
            ?: throw IllegalStateException("You are not signed in.")

        if (cachedUid == user.uid && cachedCompanyId != 0) {
            return cachedCompanyId
        }

        val profile = db.collection(USERS).document(user.uid).get().await()
        val companyId = profile.getLong("companyId")?.toInt()
            ?: throw IllegalStateException("Your account has no company profile.")

        val company = db.collection(COMPANIES).document(companyId.toString()).get().await()

        cachedUid = user.uid
        cachedCompanyId = companyId
        cachedBusinessName = company.getString("businessName") ?: ""

        return companyId
    }


    // Dashboard

    override suspend fun getDashboardStats(token: String): Response<DashboardResponse> {
        val companyId = companyId()

        val items = db.collection(INVENTORY)
            .whereEqualTo("companyId", companyId)
            .get().await()
            .documents.map { it.toInventoryItem() }

        val deliveries = db.collection(DELIVERIES)
            .whereEqualTo("companyId", companyId)
            .get().await()
            .documents.map { it.toDelivery() }

        return Response.success(
            DashboardResponse(
                success = true,
                data = DashboardStats(
                    inventoryCount = items.size,
                    deliveryCount = deliveries.size,
                    pendingCount = deliveries.count { it.status == "pending" },
                    lowStock = items.count { it.isLowStock },
                    inTransitCount = deliveries.count { it.status == "in_transit" },
                    deliveredCount = deliveries.count { it.status == "delivered" }
                )
            )
        )
    }


    // Inventory

    override suspend fun getInventory(
        token: String,
        search: String?
    ): Response<InventoryResponse> {
        val companyId = companyId()

        val all = db.collection(INVENTORY)
            .whereEqualTo("companyId", companyId)
            .get().await()
            .documents.map { it.toInventoryItem() }
            .sortedBy { it.productName.lowercase() }

        val query = search?.trim()?.lowercase().orEmpty()

        val items = if (query.isEmpty()) all else all.filter {
            it.productName.lowercase().contains(query) ||
                    it.sku.lowercase().contains(query) ||
                    (it.category?.lowercase()?.contains(query) == true)
        }

        return Response.success(
            InventoryResponse(
                success = true,
                data = items,
                summary = InventorySummary(
                    totalProducts = items.size,
                    totalUnits = items.sumOf { it.quantity },
                    lowStockCount = items.count { it.isLowStock }
                )
            )
        )
    }

    override suspend fun addInventoryItem(
        token: String,
        request: AddInventoryRequest
    ): Response<InventoryActionResponse> {
        val companyId = companyId()

        val duplicate = db.collection(INVENTORY)
            .whereEqualTo("companyId", companyId)
            .whereEqualTo("sku", request.sku)
            .get().await()

        if (!duplicate.isEmpty) {
            return fail(InventoryActionResponse(false, "A product with SKU ${request.sku} already exists."))
        }

        val id = newId()

        write {
            db.collection(INVENTORY).document(id.toString()).set(
                mapOf(
                    "id" to id,
                    "companyId" to companyId,
                    "sku" to request.sku,
                    "productName" to request.productName,
                    "category" to request.category,
                    "quantity" to request.quantity,
                    "lowStockThreshold" to request.lowStockThreshold,
                    "costPrice" to request.costPrice,
                    "sellingPrice" to request.sellingPrice,
                    "createdAt" to timestamp(),
                    "updatedAt" to timestamp()
                )
            )
        }

        return Response.success(InventoryActionResponse(true, "Product added.", id))
    }

    override suspend fun updateInventoryItem(
        token: String,
        request: UpdateInventoryRequest
    ): Response<InventoryActionResponse> {
        companyId()

        write {
            db.collection(INVENTORY).document(request.id.toString()).update(
                mapOf(
                    "quantity" to request.quantity,
                    "lowStockThreshold" to request.lowStockThreshold,
                    "costPrice" to request.costPrice,
                    "sellingPrice" to request.sellingPrice,
                    "updatedAt" to timestamp()
                )
            )
        }

        return Response.success(InventoryActionResponse(true, "Product updated.", request.id))
    }

    override suspend fun deleteInventoryItem(
        token: String,
        request: Map<String, Int>
    ): Response<InventoryActionResponse> {
        val id = request["id"]
            ?: return fail(InventoryActionResponse(false, "No product was selected."))

        companyId()

        write { db.collection(INVENTORY).document(id.toString()).delete() }

        return Response.success(InventoryActionResponse(true, "Product deleted.", id))
    }


    // Deliveries

    override suspend fun getDeliveries(
        token: String,
        status: String?,
        search: String?
    ): Response<DeliveryResponse> {
        val companyId = companyId()

        val query = search?.trim()?.lowercase().orEmpty()

        val deliveries = db.collection(DELIVERIES)
            .whereEqualTo("companyId", companyId)
            .get().await()
            .documents.map { it.toDelivery() }
            .filter { status.isNullOrBlank() || it.status == status }
            .filter {
                query.isEmpty() ||
                        it.referenceCode.lowercase().contains(query) ||
                        it.pickupAddress.lowercase().contains(query) ||
                        it.dropoffAddress.lowercase().contains(query) ||
                        it.packageDescription.lowercase().contains(query)
            }
            .sortedByDescending { it.createdAt }

        return Response.success(DeliveryResponse(success = true, data = deliveries))
    }

    override suspend fun addDelivery(
        token: String,
        request: AddDeliveryRequest
    ): Response<DeliveryActionResponse> {
        val companyId = companyId()
        val id = newId()

        write {
            db.collection(DELIVERIES).document(id.toString()).set(
                mapOf(
                    "id" to id,
                    "companyId" to companyId,
                    "referenceCode" to request.referenceCode,
                    "pickupAddress" to request.pickupAddress,
                    "dropoffAddress" to request.dropoffAddress,
                    "packageDescription" to request.packageDescription,
                    "recipientName" to request.recipientName,
                    "recipientPhone" to request.recipientPhone,
                    "scheduledDate" to request.scheduledDate,
                    "agreedCost" to request.agreedCost.toDoubleOrNull(),
                    "driverName" to null,
                    "driverPhone" to null,
                    "status" to "pending",
                    "createdAt" to timestamp(),
                    "updatedAt" to timestamp()
                )
            )
        }

        return Response.success(DeliveryActionResponse(true, "Delivery created.", id))
    }

    override suspend fun updateDelivery(
        token: String,
        request: UpdateDeliveryRequest
    ): Response<DeliveryActionResponse> {
        companyId()

        val changes = mutableMapOf<String, Any?>(
            "status" to request.status,
            "updatedAt" to timestamp()
        )

        if (request.driverName.isNotBlank()) changes["driverName"] = request.driverName
        if (request.driverPhone.isNotBlank()) changes["driverPhone"] = request.driverPhone
        if (request.note.isNotBlank()) changes["notes"] = FieldValue.arrayUnion(request.note)

        write {
            db.collection(DELIVERIES).document(request.id.toString()).update(changes)
        }

        return Response.success(DeliveryActionResponse(true, "Delivery updated.", request.id))
    }


    // Users / drivers

    override suspend fun getDrivers(token: String): Response<DriversResponse> {
        val companyId = companyId()

        val drivers = db.collection(USERS)
            .whereEqualTo("companyId", companyId)
            .whereEqualTo("role", "driver")
            .get().await()
            .documents.map {
                User(
                    fullname = it.getString("fullname") ?: "",
                    email = it.getString("email") ?: "",
                    role = "driver",
                    createdAt = it.getString("createdAt") ?: ""
                )
            }

        return Response.success(DriversResponse(success = true, data = drivers))
    }


    // Marketplace

    override suspend fun getMarketplace(
        token: String,
        search: String?,
        type: String?,
        province: String?
    ): Response<MarketplaceResponse> {
        val companyId = companyId()
        val query = search?.trim()?.lowercase().orEmpty()

        val listings = db.collection(LISTINGS)
            .whereEqualTo("status", "active")
            .get().await()
            .documents.map { it.toListing() }
            .filter { type.isNullOrBlank() || it.listingType == type }
            .filter { province.isNullOrBlank() || it.province == province }
            .filter {
                query.isEmpty() ||
                        it.title.lowercase().contains(query) ||
                        (it.description?.lowercase()?.contains(query) == true) ||
                        (it.company?.lowercase()?.contains(query) == true)
            }
            .sortedByDescending { it.createdAt }

        val received = db.collection(QUOTES)
            .whereEqualTo("sellerCompanyId", companyId)
            .get().await()
            .documents.map { it.toQuote() }

        val sent = db.collection(QUOTES)
            .whereEqualTo("buyerCompanyId", companyId)
            .get().await()
            .documents.map { it.toQuote() }

        return Response.success(
            MarketplaceResponse(
                success = true,
                data = listings,
                receivedQuotes = received,
                sentQuotes = sent
            )
        )
    }

    override suspend fun publishMarketplaceListing(
        token: String,
        request: PublishListingRequest
    ): Response<MarketplaceActionResponse> {
        val companyId = companyId()
        val id = newId()

        write {
            db.collection(LISTINGS).document(id.toString()).set(
                mapOf(
                    "id" to id,
                    "companyId" to companyId,
                    "businessName" to cachedBusinessName,
                    "listingType" to request.listingType,
                    "title" to request.title,
                    "description" to request.description,
                    "province" to request.province,
                    "city" to request.city,
                    "price" to request.price,
                    "currency" to "ZAR",
                    "status" to "active",
                    "createdAt" to timestamp()
                )
            )
        }

        return Response.success(MarketplaceActionResponse(true, "Listing published.", id, "active"))
    }

    override suspend fun requestMarketplaceQuote(
        token: String,
        request: RequestQuoteRequest
    ): Response<MarketplaceActionResponse> {
        val companyId = companyId()

        val listing = db.collection(LISTINGS)
            .document(request.listingId.toString())
            .get().await()

        if (!listing.exists()) {
            return fail(MarketplaceActionResponse(false, "That listing is no longer available."))
        }

        val sellerCompanyId = listing.getLong("companyId")?.toInt() ?: 0

        if (sellerCompanyId == companyId) {
            return fail(MarketplaceActionResponse(false, "You cannot request a quote on your own listing."))
        }

        val id = newId()

        write {
            db.collection(QUOTES).document(id.toString()).set(
                mapOf(
                    "id" to id,
                    "listingId" to request.listingId,
                    "title" to (listing.getString("title") ?: ""),
                    "buyerCompanyId" to companyId,
                    "buyerName" to cachedBusinessName,
                    "sellerCompanyId" to sellerCompanyId,
                    "sellerName" to (listing.getString("businessName") ?: ""),
                    "message" to request.message,
                    "status" to "pending",
                    "createdAt" to timestamp(),
                    "updatedAt" to timestamp()
                )
            )
        }

        return Response.success(MarketplaceActionResponse(true, "Quote request sent.", id, "pending"))
    }

    override suspend fun replyToMarketplaceQuote(
        token: String,
        request: QuoteReplyRequest
    ): Response<MarketplaceActionResponse> {
        companyId()

        write {
            db.collection(QUOTES).document(request.quoteId.toString()).update(
                mapOf(
                    "proposedPrice" to request.proposedPrice,
                    "replyMessage" to request.replyMessage,
                    "status" to "quoted",
                    "updatedAt" to timestamp()
                )
            )
        }

        return Response.success(MarketplaceActionResponse(true, "Quote sent.", request.quoteId, "quoted"))
    }

    override suspend fun updateMarketplaceQuoteStatus(
        token: String,
        request: QuoteStatusRequest
    ): Response<MarketplaceActionResponse> {
        companyId()

        write {
            db.collection(QUOTES).document(request.quoteId.toString()).update(
                mapOf(
                    "status" to request.status,
                    "updatedAt" to timestamp()
                )
            )
        }

        return Response.success(
            MarketplaceActionResponse(true, "Quote ${request.status}.", request.quoteId, request.status)
        )
    }


    // Helpers

    private suspend fun write(block: () -> com.google.android.gms.tasks.Task<Void>) {
        withTimeout(TIMEOUT_MS) { block().await() }
    }

    private fun <T> fail(body: T): Response<T> = Response.success(body)

    private fun newId(): Int = Random.nextInt(100_000, Int.MAX_VALUE)

    private fun timestamp(): String =
        SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).format(Date())


    // Firestore document -> model mapping

    private fun DocumentSnapshot.int(field: String, default: Int = 0): Int =
        getLong(field)?.toInt() ?: default

    private fun DocumentSnapshot.toInventoryItem() = InventoryItem(
        id = int("id"),
        sku = getString("sku") ?: "",
        productName = getString("productName") ?: "",
        category = getString("category"),
        quantity = int("quantity"),
        lowStockThreshold = int("lowStockThreshold", 5),
        costPrice = getDouble("costPrice") ?: 0.0,
        sellingPrice = getDouble("sellingPrice") ?: 0.0,
        createdAt = getString("createdAt") ?: "",
        updatedAt = getString("updatedAt")
    )

    private fun DocumentSnapshot.toDelivery() = Delivery(
        id = int("id"),
        referenceCode = getString("referenceCode") ?: "",
        pickupAddress = getString("pickupAddress") ?: "",
        dropoffAddress = getString("dropoffAddress") ?: "",
        packageDescription = getString("packageDescription") ?: "",
        recipientName = getString("recipientName"),
        recipientPhone = getString("recipientPhone"),
        scheduledDate = getString("scheduledDate"),
        agreedCost = getDouble("agreedCost"),
        driverName = getString("driverName"),
        driverPhone = getString("driverPhone"),
        status = getString("status") ?: "pending",
        createdAt = getString("createdAt") ?: "",
        updatedAt = getString("updatedAt") ?: ""
    )

    private fun DocumentSnapshot.toListing() = MarketplaceListing(
        id = int("id"),
        companyId = int("companyId"),
        listingType = getString("listingType") ?: "",
        title = getString("title") ?: "",
        description = getString("description"),
        province = getString("province"),
        city = getString("city"),
        price = getDouble("price"),
        currency = getString("currency") ?: "ZAR",
        status = getString("status"),
        createdAt = getString("createdAt"),
        company = getString("businessName")
    )

    private fun DocumentSnapshot.toQuote() = MarketplaceQuote(
        id = int("id"),
        listingId = int("listingId"),
        buyerCompanyId = int("buyerCompanyId"),
        message = getString("message"),
        status = getString("status"),
        createdAt = getString("createdAt"),
        updatedAt = getString("updatedAt"),
        title = getString("title"),
        buyerName = getString("buyerName"),
        sellerName = getString("sellerName")
    )
}
