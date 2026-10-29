package com.example.logicostapp3.api

import com.example.logicostapp3.model.*
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.HTTP
import retrofit2.http.Header
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Query
import com.example.logicostapp3.model.MarketplaceResponse
import com.example.logicostapp3.model.MarketplaceActionResponse
import com.example.logicostapp3.model.PublishListingRequest
import com.example.logicostapp3.model.RequestQuoteRequest
import com.example.logicostapp3.model.QuoteReplyRequest
import com.example.logicostapp3.model.QuoteStatusRequest


interface ApiService {

    // Authentication

    @POST("login.php")
    suspend fun login(
        @Body request: LoginRequest
    ): Response<AuthResponse>

    @POST("register.php")
    suspend fun register(
        @Body request: RegisterRequest
    ): Response<AuthResponse>


    // Dashboard

    @GET("dashboard.php")
    suspend fun getDashboardStats(
        @Header("Authorization") token: String
    ): Response<DashboardResponse>


    // Inventory

    @GET("inventory.php")
    suspend fun getInventory(
        @Header("Authorization") token: String,
        @Query("q") search: String? = null
    ): Response<InventoryResponse>

    @POST("inventory.php")
    suspend fun addInventoryItem(
        @Header("Authorization") token: String,
        @Body request: AddInventoryRequest
    ): Response<InventoryActionResponse>

    @PUT("inventory.php")
    suspend fun updateInventoryItem(
        @Header("Authorization") token: String,
        @Body request: UpdateInventoryRequest
    ): Response<InventoryActionResponse>

    @HTTP(method = "DELETE", path = "inventory.php", hasBody = true)
    suspend fun deleteInventoryItem(
        @Header("Authorization") token: String,
        @Body request: Map<String, Int>
    ): Response<InventoryActionResponse>


    // Deliveries

    @GET("deliveries.php")
    suspend fun getDeliveries(
        @Header("Authorization") token: String,
        @Query("status") status: String? = null,
        @Query("q") search: String? = null
    ): Response<DeliveryResponse>

    @POST("deliveries.php")
    suspend fun addDelivery(
        @Header("Authorization") token: String,
        @Body request: AddDeliveryRequest
    ): Response<DeliveryActionResponse>

    @PUT("deliveries.php")
    suspend fun updateDelivery(
        @Header("Authorization") token: String,
        @Body request: UpdateDeliveryRequest
    ): Response<DeliveryActionResponse>


    // Users / drivers

    @GET("users.php")
    suspend fun getDrivers(
        @Header("Authorization") token: String
    ): Response<DriversResponse>


    // Marketplace

    // Retrieve published listings and quotation requests
    @GET("marketplace.php")
    suspend fun getMarketplace(
        @Header("Authorization") token: String,
        @Query("q") search: String? = null,
        @Query("type") type: String? = null,
        @Query("province") province: String? = null
    ): Response<MarketplaceResponse>

    // Publish a new marketplace listing
    @POST("marketplace.php")
    suspend fun publishMarketplaceListing(
        @Header("Authorization") token: String,
        @Body request: PublishListingRequest
    ): Response<MarketplaceActionResponse>

    // Request a quote from another company
    @POST("marketplace.php")
    suspend fun requestMarketplaceQuote(
        @Header("Authorization") token: String,
        @Body request: RequestQuoteRequest
    ): Response<MarketplaceActionResponse>

    // Reply to a quotation request with a proposed price
    @POST("marketplace.php")
    suspend fun replyToMarketplaceQuote(
        @Header("Authorization") token: String,
        @Body request: QuoteReplyRequest
    ): Response<MarketplaceActionResponse>

    // Accept, decline, or close a quotation request
    @POST("marketplace.php")
    suspend fun updateMarketplaceQuoteStatus(
        @Header("Authorization") token: String,
        @Body request: QuoteStatusRequest
    ): Response<MarketplaceActionResponse>
}


data class DriversResponse(
    val success: Boolean,
    val data: List<User>? = null
)
