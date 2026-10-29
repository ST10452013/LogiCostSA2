package com.example.logicostapp3.ui.inventory

import android.graphics.Color
import android.view.LayoutInflater
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.example.logicostapp3.databinding.ItemInventoryBinding
import com.example.logicostapp3.model.InventoryItem
import java.util.Locale

class InventoryAdapter(
    private val onUpdate: (InventoryItem, Int, Int, Double, Double) -> Unit,
    private val onDelete: (InventoryItem) -> Unit
) : ListAdapter<InventoryItem, InventoryAdapter.ViewHolder>(DIFF) {

    companion object {
        private val DIFF = object : DiffUtil.ItemCallback<InventoryItem>() {
            override fun areItemsTheSame(
                oldItem: InventoryItem,
                newItem: InventoryItem
            ) = oldItem.id == newItem.id

            override fun areContentsTheSame(
                oldItem: InventoryItem,
                newItem: InventoryItem
            ) = oldItem == newItem
        }

        private fun money(value: Double): String =
            String.format(Locale.US, "R %.2f", value)
    }

    inner class ViewHolder(
        private val binding: ItemInventoryBinding
    ) : RecyclerView.ViewHolder(binding.root) {

        fun bind(item: InventoryItem) {
            binding.tvProductName.text = item.productName

            val category = item.category?.takeIf { it.isNotBlank() }
                ?: "Uncategorised"

            binding.tvSkuCategory.text = "SKU: ${item.sku}  •  $category"

            binding.etQuantity.setText(item.quantity.toString())
            binding.etThreshold.setText(item.lowStockThreshold.toString())
            binding.etCostPrice.setText(
                String.format(Locale.US, "%.2f", item.costPrice)
            )
            binding.etSellingPrice.setText(
                String.format(Locale.US, "%.2f", item.sellingPrice)
            )

            if (item.isLowStock) {
                binding.tvStatus.text = "⚠ Low stock"
                binding.tvStatus.setTextColor(Color.parseColor("#D32F2F"))
                binding.cardRoot.strokeColor = Color.parseColor("#FFCDD2")
            } else {
                binding.tvStatus.text = "In stock"
                binding.tvStatus.setTextColor(Color.parseColor("#388E3C"))
                binding.cardRoot.strokeColor = Color.parseColor("#C8E6C9")
            }

            binding.btnUpdate.setOnClickListener {
                val quantity = binding.etQuantity.text?.toString()
                    ?.toIntOrNull()
                val threshold = binding.etThreshold.text?.toString()
                    ?.toIntOrNull()
                val cost = binding.etCostPrice.text?.toString()
                    ?.toDoubleOrNull()
                val selling = binding.etSellingPrice.text?.toString()
                    ?.toDoubleOrNull()

                if (
                    quantity == null || quantity < 0 ||
                    threshold == null || threshold < 0 ||
                    cost == null || !cost.isFinite() || cost < 0 ||
                    selling == null || !selling.isFinite() || selling < 0
                ) {
                    binding.etQuantity.error =
                        "Enter valid non-negative values"
                    return@setOnClickListener
                }

                onUpdate(item, quantity, threshold, cost, selling)
            }

            binding.btnDelete.setOnClickListener {
                onDelete(item)
            }
        }
    }

    override fun onCreateViewHolder(
        parent: ViewGroup,
        viewType: Int
    ): ViewHolder {
        val binding = ItemInventoryBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return ViewHolder(binding)
    }

    override fun onBindViewHolder(holder: ViewHolder, position: Int) {
        holder.bind(getItem(position))
    }
}