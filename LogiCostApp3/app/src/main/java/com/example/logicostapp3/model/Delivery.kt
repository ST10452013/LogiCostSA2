package com.example.logicostapp3.model

import com.google.gson.annotations.SerializedName

data class Delivery(
    @SerializedName("id")
    val id: Int = 0,

    @SerializedName("reference_code")
    val referenceCode: String = "",

    @SerializedName("pickup_address")
    val pickupAddress: String = "",

    @SerializedName("dropoff_address")
    val dropoffAddress: String = "",

    @SerializedName("package_description")
    val packageDescription: String = "",

    @SerializedName("recipient_name")
    val recipientName: String? = null,

    @SerializedName("recipient_phone")
    val recipientPhone: String? = null,

    @SerializedName("scheduled_date")
    val scheduledDate: String? = null,

    @SerializedName("agreed_cost")
    val agreedCost: Double? = null,

    @SerializedName("driver_name")
    val driverName: String? = null,

    @SerializedName("driver_phone")
    val driverPhone: String? = null,

    @SerializedName("status")
    val status: String = "pending",

    @SerializedName("created_at")
    val createdAt: String = "",

    @SerializedName("updated_at")
    val updatedAt: String = ""
) {
    val statusEmoji: String
        get() = when (status) {
            "pending" -> "⏳"
            "assigned" -> "👤"
            "in_transit" -> "🚚"
            "delivered" -> "✅"
            "cancelled" -> "❌"
            else -> "❓"
        }

    val statusLabel: String
        get() = when (status) {
            "pending" -> "Pending"
            "assigned" -> "Assigned"
            "in_transit" -> "In Transit"
            "delivered" -> "Delivered"
            "cancelled" -> "Cancelled"
            else -> status
        }
}

data class DeliveryResponse(
    @SerializedName("success")
    val success: Boolean,

    @SerializedName("message")
    val message: String = "",

    @SerializedName("data")
    val data: List<Delivery>? = null
)

data class DeliveryActionResponse(
    @SerializedName("success")
    val success: Boolean,

    @SerializedName("message")
    val message: String = "",

    @SerializedName("id")
    val id: Int? = null
)

data class AddDeliveryRequest(
    @SerializedName("reference_code")
    val referenceCode: String,

    @SerializedName("pickup_address")
    val pickupAddress: String,

    @SerializedName("dropoff_address")
    val dropoffAddress: String,

    @SerializedName("package_description")
    val packageDescription: String,

    @SerializedName("recipient_name")
    val recipientName: String = "",

    @SerializedName("recipient_phone")
    val recipientPhone: String = "",

    @SerializedName("scheduled_date")
    val scheduledDate: String = "",

    @SerializedName("agreed_cost")
    val agreedCost: String = ""
)

data class UpdateDeliveryRequest(
    @SerializedName("id")
    val id: Int,

    @SerializedName("status")
    val status: String,

    @SerializedName("driver_name")
    val driverName: String = "",

    @SerializedName("driver_phone")
    val driverPhone: String = "",

    @SerializedName("note")
    val note: String = ""
)
