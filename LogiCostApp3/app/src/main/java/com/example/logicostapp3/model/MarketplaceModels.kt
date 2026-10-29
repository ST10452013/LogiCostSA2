package com.example.logicostapp3.model

import com.google.gson.annotations.SerializedName

// A listing returned by marketplace.php
data class MarketplaceListing(
    val id: Int,

    @SerializedName("company_id")
    val companyId: Int,

    @SerializedName("listing_type")
    val listingType: String,

    val title: String,

    val description: String? = null,

    val province: String? = null,

    val city: String? = null,

    val price: Double? = null,

    val currency: String? = "ZAR",

    val status: String? = null,

    @SerializedName("created_at")
    val createdAt: String? = null,

    @SerializedName("business_name")
    val company: String? = null
)

// A quotation request returned by the API
data class MarketplaceQuote(
    val id: Int,

    @SerializedName("listing_id")
    val listingId: Int,

    @SerializedName("buyer_company_id")
    val buyerCompanyId: Int,

    val message: String? = null,

    val status: String? = null,

    @SerializedName("created_at")
    val createdAt: String? = null,

    @SerializedName("updated_at")
    val updatedAt: String? = null,

    val title: String? = null,

    @SerializedName("buyer_name")
    val buyerName: String? = null,

    @SerializedName("seller_name")
    val sellerName: String? = null
)

// GET marketplace.php response
data class MarketplaceResponse(
    val success: Boolean,

    val data: List<MarketplaceListing>? = emptyList(),

    @SerializedName("received_quotes")
    val receivedQuotes: List<MarketplaceQuote>? = emptyList(),

    @SerializedName("sent_quotes")
    val sentQuotes: List<MarketplaceQuote>? = emptyList(),

    val message: String? = null
)

// POST publish request
data class PublishListingRequest(
    val action: String = "publish",

    @SerializedName("listing_type")
    val listingType: String,

    val title: String,

    val description: String,

    val province: String,

    val city: String,

    val price: Double? = null
)

// POST quotation request
data class RequestQuoteRequest(
    val action: String = "request_quote",

    @SerializedName("listing_id")
    val listingId: Int,

    val message: String
)

// POST seller quotation reply
data class QuoteReplyRequest(
    val action: String = "quote_reply",

    @SerializedName("quote_id")
    val quoteId: Int,

    @SerializedName("proposed_price")
    val proposedPrice: Double,

    @SerializedName("reply_message")
    val replyMessage: String
)

// POST quotation status update
data class QuoteStatusRequest(
    val action: String = "quote_status",

    @SerializedName("quote_id")
    val quoteId: Int,

    val status: String
)

// Response returned by marketplace POST actions
data class MarketplaceActionResponse(
    val success: Boolean,

    val message: String? = null,

    val id: Int? = null,

    val status: String? = null
)