package com.example.logicostapp3.model

import com.google.gson.annotations.SerializedName

data class DashboardStats(
    @SerializedName("inventory_count")
    val inventoryCount: Int = 0,

    @SerializedName("delivery_count")
    val deliveryCount: Int = 0,

    @SerializedName("pending_count")
    val pendingCount: Int = 0,

    @SerializedName("low_stock")
    val lowStock: Int = 0,

    @SerializedName("in_transit_count")
    val inTransitCount: Int = 0,

    @SerializedName("delivered_count")
    val deliveredCount: Int = 0
)

data class DashboardResponse(
    @SerializedName("success")
    val success: Boolean,

    @SerializedName("message")
    val message: String? = null,

    @SerializedName("data")
    val data: DashboardStats? = null
)
