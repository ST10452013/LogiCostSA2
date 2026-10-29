package com.example.logicostapp3

import android.app.AlertDialog
import android.os.Bundle
import android.view.Gravity
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.*
import androidx.core.widget.doAfterTextChanged
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import com.example.logicostapp3.api.FirebaseService
import com.example.logicostapp3.api.SessionManager
import com.example.logicostapp3.model.MarketplaceActionResponse
import com.example.logicostapp3.model.MarketplaceResponse
import com.example.logicostapp3.model.PublishListingRequest
import com.example.logicostapp3.model.RequestQuoteRequest
import com.example.logicostapp3.model.MarketplaceListing as ApiListing
import com.google.gson.JsonParser
import kotlinx.coroutines.launch
import java.util.Locale

// UI model used by the existing marketplace card layout
data class MarketplaceListing(
    val id: Int,
    val title: String,
    val company: String,
    val description: String,
    val type: String,
    val city: String,
    val province: String,
    val price: Double?
)

class MarketplaceFragment : Fragment() {

    private lateinit var listingsLayout: LinearLayout
    private lateinit var searchInput: EditText
    private lateinit var typeSpinner: Spinner

    private lateinit var sessionManager: SessionManager

    // This list is populated by the server, not demo records.
    private val listings = mutableListOf<MarketplaceListing>()

    private var isLoading = false

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        return inflater.inflate(
            R.layout.fragment_marketplace,
            container,
            false
        )
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        sessionManager = SessionManager(requireContext())

        listingsLayout =
            view.findViewById(R.id.layoutMarketplaceListings)

        searchInput =
            view.findViewById(R.id.etMarketplaceSearch)

        typeSpinner =
            view.findViewById(R.id.spinnerListingType)

        // Set up the listing filter.
        typeSpinner.adapter = ArrayAdapter(
            requireContext(),
            android.R.layout.simple_spinner_dropdown_item,
            listOf("All types", "Product", "Service", "Delivery")
        )

        view.findViewById<Button>(R.id.btnPublishListing)
            .setOnClickListener {
                showPublishDialog()
            }

        searchInput.doAfterTextChanged {
            renderListings()
        }

        typeSpinner.onItemSelectedListener =
            object : AdapterView.OnItemSelectedListener {

                override fun onItemSelected(
                    parent: AdapterView<*>?,
                    view: View?,
                    position: Int,
                    id: Long
                ) {
                    renderListings()
                }

                override fun onNothingSelected(
                    parent: AdapterView<*>?
                ) = Unit
            }

        loadListings()
    }

    override fun onResume() {
        super.onResume()

        // Refresh listings when the user returns to this screen.
        if (::listingsLayout.isInitialized) {
            loadListings()
        }
    }

    private fun getAuthToken(): String? {
        if (!sessionManager.isLoggedIn()) {
            return null
        }

        val token = sessionManager.getToken()

        // getToken() already includes "Bearer ".
        if (token == "Bearer " ||
            token == "Bearer DEV_GUEST_TOKEN"
        ) {
            return null
        }

        return token
    }

    private fun loadListings() {
        val token = getAuthToken()

        if (token == null) {
            Toast.makeText(
                requireContext(),
                "Please sign in with an active company account.",
                Toast.LENGTH_LONG
            ).show()
            return
        }

        isLoading = true
        renderListings()

        viewLifecycleOwner.lifecycleScope.launch {
            try {
                val response = FirebaseService.instance
                    .getMarketplace(token)

                if (!isAdded) return@launch

                if (response.isSuccessful) {
                    val result = response.body()

                    if (result?.success == true) {
                        listings.clear()

                        listings.addAll(
                            (result.data ?: emptyList()).map {
                                it.toUiListing()
                            }
                        )

                        renderListings()
                    } else {
                        showMessage(
                            result?.message
                                ?: "Could not load marketplace listings."
                        )
                    }
                } else {
                    showMessage(
                        extractServerMessage(
                            response.errorBody()?.string()
                        )
                    )
                }
            } catch (e: Exception) {
                if (isAdded) {
                    showMessage(
                        "Could not connect to LogiCost. " +
                                "Check your network and API server."
                    )
                    android.util.Log.e(
                        "Marketplace",
                        "Loading listings failed",
                        e
                    )
                }
            } finally {
                if (isAdded) {
                    isLoading = false
                    renderListings()
                }
            }
        }
    }

    private fun ApiListing.toUiListing(): MarketplaceListing {
        return MarketplaceListing(
            id = id,
            title = title,
            company = company ?: "Business",
            description = description.orEmpty(),
            type = listingType.replaceFirstChar {
                if (it.isLowerCase()) it.titlecase(Locale.ROOT)
                else it.toString()
            },
            city = city.orEmpty(),
            province = province.orEmpty(),
            price = price
        )
    }

    private fun renderListings() {
        if (!::listingsLayout.isInitialized) return

        listingsLayout.removeAllViews()

        if (isLoading && listings.isEmpty()) {
            addMessage("Loading marketplace listings...")
            return
        }

        val query = searchInput.text.toString()
            .trim()
            .lowercase(Locale.ROOT)

        val selectedType =
            typeSpinner.selectedItem?.toString() ?: "All types"

        val filtered = listings.filter { listing ->
            val matchesSearch =
                listing.title.lowercase(Locale.ROOT).contains(query) ||
                        listing.company.lowercase(Locale.ROOT).contains(query) ||
                        listing.description.lowercase(Locale.ROOT).contains(query) ||
                        listing.city.lowercase(Locale.ROOT).contains(query) ||
                        listing.province.lowercase(Locale.ROOT).contains(query)

            val matchesType =
                selectedType == "All types" ||
                        listing.type.equals(selectedType, ignoreCase = true)

            matchesSearch && matchesType
        }

        if (filtered.isEmpty()) {
            addMessage(
                if (isLoading) {
                    "Refreshing marketplace..."
                } else {
                    "No published listings found. Try another search."
                }
            )
            return
        }

        filtered.forEach { listing ->
            val card = layoutInflater.inflate(
                R.layout.item_marketplace_listing,
                listingsLayout,
                false
            )

            card.findViewById<TextView>(R.id.tvListingType).text =
                listing.type.uppercase(Locale.ROOT)

            card.findViewById<TextView>(R.id.tvListingTitle).text =
                listing.title

            card.findViewById<TextView>(R.id.tvListingCompany).text =
                listing.company

            card.findViewById<TextView>(R.id.tvListingLocation).text =
                listOf(listing.city, listing.province)
                    .filter { it.isNotBlank() }
                    .joinToString(", ")
                    .ifBlank { "Location not specified" }

            card.findViewById<TextView>(R.id.tvListingDescription).text =
                listing.description.ifBlank {
                    "No description provided."
                }

            card.findViewById<TextView>(R.id.tvListingPrice).text =
                listing.price?.let {
                    "From R ${"%,.2f".format(Locale.US, it)}"
                } ?: "Price on request"

            card.findViewById<Button>(R.id.btnRequestQuote)
                .setOnClickListener {
                    showQuoteDialog(listing)
                }

            listingsLayout.addView(card)
        }
    }

    private fun addMessage(message: String) {
        val text = TextView(requireContext()).apply {
            this.text = message
            textSize = 14f
            setTextColor(0xFF687386.toInt())
            setPadding(0, 16, 0, 16)
        }

        listingsLayout.addView(text)
    }

    private fun showPublishDialog() {
        val form = LinearLayout(requireContext()).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(24, 0, 24, 0)
        }

        fun addField(hintText: String): EditText {
            val field = EditText(requireContext()).apply {
                hint = hintText
                isSingleLine = hintText != "Description"

                if (hintText == "Description") {
                    minLines = 3
                    gravity = Gravity.TOP
                }
            }

            form.addView(field)
            return field
        }

        val title = addField("Listing title")
        val description = addField("Description")

        val type = Spinner(requireContext()).apply {
            adapter = ArrayAdapter(
                requireContext(),
                android.R.layout.simple_spinner_dropdown_item,
                listOf("Product", "Service", "Delivery")
            )
        }
        form.addView(type)

        val city = addField("City")
        val province = addField("Province")
        val price = addField("Price in rand (optional)")

        price.inputType =
            android.text.InputType.TYPE_CLASS_NUMBER or
                    android.text.InputType.TYPE_NUMBER_FLAG_DECIMAL

        val dialog = AlertDialog.Builder(requireContext())
            .setTitle("Publish a listing")
            .setView(ScrollView(requireContext()).apply {
                addView(form)
            })
            .setNegativeButton("Cancel", null)
            .setPositiveButton("Publish", null)
            .create()

        dialog.setOnShowListener {
            dialog.getButton(AlertDialog.BUTTON_POSITIVE)
                .setOnClickListener {

                    val amountText = price.text.toString().trim()
                    val amount = if (amountText.isEmpty()) {
                        null
                    } else {
                        amountText.toDoubleOrNull()
                    }

                    if (
                        title.text.isBlank() ||
                        description.text.isBlank() ||
                        city.text.isBlank() ||
                        province.text.isBlank() ||
                        (amountText.isNotEmpty() &&
                                (amount == null || !amount.isFinite() || amount < 0))
                    ) {
                        showMessage(
                            "Complete all required fields and enter a valid price."
                        )
                        return@setOnClickListener
                    }

                    val token = getAuthToken()

                    if (token == null) {
                        showMessage(
                            "Please sign in with an active company account."
                        )
                        return@setOnClickListener
                    }

                    val request = PublishListingRequest(
                        listingType = type.selectedItem.toString()
                            .lowercase(Locale.ROOT),
                        title = title.text.toString().trim(),
                        description = description.text.toString().trim(),
                        city = city.text.toString().trim(),
                        province = province.text.toString().trim(),
                        price = amount
                    )

                    dialog.dismiss()
                    publishListing(token, request)
                }
        }

        dialog.show()
    }

    private fun publishListing(
        token: String,
        request: PublishListingRequest
    ) {
        viewLifecycleOwner.lifecycleScope.launch {
            try {
                val response = FirebaseService.instance
                    .publishMarketplaceListing(token, request)

                if (!isAdded) return@launch

                if (response.isSuccessful &&
                    response.body()?.success == true
                ) {
                    showMessage(
                        response.body()?.message
                            ?: "Listing published successfully."
                    )
                    loadListings()
                } else {
                    showMessage(
                        extractServerMessage(
                            response.errorBody()?.string()
                        )
                    )
                }
            } catch (e: Exception) {
                if (isAdded) {
                    showMessage(
                        "Could not publish listing. Check your connection."
                    )
                    android.util.Log.e(
                        "Marketplace",
                        "Publishing listing failed",
                        e
                    )
                }
            }
        }
    }

    private fun showQuoteDialog(listing: MarketplaceListing) {
        val input = EditText(requireContext()).apply {
            hint = "Describe what you need"
            minLines = 3
            gravity = Gravity.TOP
        }

        val dialog = AlertDialog.Builder(requireContext())
            .setTitle("Request a quote")
            .setMessage("Listing: ${listing.title}")
            .setView(input)
            .setNegativeButton("Cancel", null)
            .setPositiveButton("Submit", null)
            .create()

        dialog.setOnShowListener {
            dialog.getButton(AlertDialog.BUTTON_POSITIVE)
                .setOnClickListener {
                    val message = input.text.toString().trim()

                    if (message.isBlank()) {
                        showMessage(
                            "Please describe your requirements."
                        )
                        return@setOnClickListener
                    }

                    val token = getAuthToken()

                    if (token == null) {
                        showMessage(
                            "Please sign in with an active company account."
                        )
                        return@setOnClickListener
                    }

                    dialog.dismiss()

                    submitQuote(
                        token,
                        RequestQuoteRequest(
                            listingId = listing.id,
                            message = message
                        )
                    )
                }
        }

        dialog.show()
    }

    private fun submitQuote(
        token: String,
        request: RequestQuoteRequest
    ) {
        viewLifecycleOwner.lifecycleScope.launch {
            try {
                val response = FirebaseService.instance
                    .requestMarketplaceQuote(token, request)

                if (!isAdded) return@launch

                if (response.isSuccessful &&
                    response.body()?.success == true
                ) {
                    showMessage(
                        response.body()?.message
                            ?: "Quotation request sent."
                    )
                } else {
                    showMessage(
                        extractServerMessage(
                            response.errorBody()?.string()
                        )
                    )
                }
            } catch (e: Exception) {
                if (isAdded) {
                    showMessage(
                        "Could not submit quotation request. " +
                                "Check your connection."
                    )
                    android.util.Log.e(
                        "Marketplace",
                        "Quote submission failed",
                        e
                    )
                }
            }
        }
    }

    private fun extractServerMessage(raw: String?): String {
        if (raw.isNullOrBlank()) {
            return "The server returned an error. Please try again."
        }

        return try {
            JsonParser.parseString(raw)
                .asJsonObject
                .get("message")
                ?.asString
                ?: "The request failed. Please try again."
        } catch (_: Exception) {
            "The request failed. Please try again."
        }
    }

    private fun showMessage(message: String) {
        if (!isAdded) return

        Toast.makeText(
            requireContext(),
            message,
            Toast.LENGTH_LONG
        ).show()
    }
}
