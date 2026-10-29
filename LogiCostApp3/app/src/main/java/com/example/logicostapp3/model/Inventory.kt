package com.example.logicostapp3.model

import com.google.gson.annotations.SerializedName

data class InventoryItem(
    @SerializedName("id")
    val id: Int = 0,

    @SerializedName("sku")
    val sku: String = "",

    @SerializedName("product_name")
    val productName: String = "",

    @SerializedName("category")
    val category: String? = null,

    @SerializedName("quantity")
    val quantity: Int = 0,

    @SerializedName("low_stock_threshold")
    val lowStockThreshold: Int = 5,

    @SerializedName("cost_price")
    val costPrice: Double = 0.0,

    @SerializedName("selling_price")
    val sellingPrice: Double = 0.0,

    @SerializedName("created_at")
    val createdAt: String = "",

    @SerializedName("updated_at")
    val updatedAt: String? = null
) {
    val isLowStock: Boolean
        get() = quantity <= lowStockThreshold
}

data class InventoryResponse(
    @SerializedName("success")
    val success: Boolean,

    @SerializedName("message")
    val message: String = "",

    @SerializedName("data")
    val data: List<InventoryItem>? = null,

    @SerializedName("summary")
    val summary: InventorySummary? = null
)

data class InventorySummary(
    @SerializedName("total_products")
    val totalProducts: Int = 0,

    @SerializedName("total_units")
    val totalUnits: Int = 0,

    @SerializedName("low_stock_count")
    val lowStockCount: Int = 0
)

data class InventoryActionResponse(
    @SerializedName("success")
    val success: Boolean,

    @SerializedName("message")
    val message: String = "",

    @SerializedName("id")
    val id: Int? = null
)

data class AddInventoryRequest(
    @SerializedName("sku")
    val sku: String,

    @SerializedName("product_name")
    val productName: String,

    @SerializedName("category")
    val category: String,

    @SerializedName("quantity")
    val quantity: Int,

    @SerializedName("low_stock_threshold")
    val lowStockThreshold: Int,

    @SerializedName("cost_price")
    val costPrice: Double,

    @SerializedName("selling_price")
    val sellingPrice: Double
)

data class UpdateInventoryRequest(
    @SerializedName("id")
    val id: Int,

    @SerializedName("quantity")
    val quantity: Int,

    @SerializedName("low_stock_threshold")
    val lowStockThreshold: Int,

    @SerializedName("cost_price")
    val costPrice: Double,

    @SerializedName("selling_price")
    val sellingPrice: Double
)