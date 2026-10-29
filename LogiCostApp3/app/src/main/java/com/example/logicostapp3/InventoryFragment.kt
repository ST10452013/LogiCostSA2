package com.example.logicostapp3.ui.inventory

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.appcompat.app.AlertDialog
import androidx.core.widget.doAfterTextChanged
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import com.example.logicostapp3.api.AppSettings
import com.example.logicostapp3.api.FirebaseService
import com.example.logicostapp3.api.SessionManager
import com.example.logicostapp3.databinding.DialogAddInventoryBinding
import com.example.logicostapp3.databinding.FragmentInventoryBinding
import com.example.logicostapp3.model.AddInventoryRequest
import com.example.logicostapp3.model.InventoryItem
import com.example.logicostapp3.model.UpdateInventoryRequest
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import java.util.Locale

class InventoryFragment : Fragment() {

    private var _binding: FragmentInventoryBinding? = null
    private val binding get() = _binding!!

    private lateinit var session: SessionManager
    private lateinit var adapter: InventoryAdapter

    private var loadJob: Job? = null
    private var searchJob: Job? = null

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentInventoryBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        session = SessionManager(requireContext())

        adapter = InventoryAdapter(
            onUpdate = { item, quantity, threshold, cost, selling ->
                updateInventory(item, quantity, threshold, cost, selling)
            },
            onDelete = { item ->
                confirmDelete(item)
            }
        )

        binding.rvInventory.layoutManager = LinearLayoutManager(requireContext())
        binding.rvInventory.adapter = adapter

        binding.fabAdd.setOnClickListener {
            showAddDialog()
        }

        binding.swipeRefresh.setOnRefreshListener {
            loadInventory()
        }

        // Search after the user pauses typing.
        binding.etInventorySearch.doAfterTextChanged { editable ->
            searchJob?.cancel()
            val query = editable?.toString().orEmpty()

            searchJob = viewLifecycleOwner.lifecycleScope.launch {
                delay(350)
                loadInventory(query)
            }
        }

        loadInventory()
    }

    private fun loadInventory(
        search: String = binding.etInventorySearch.text?.toString().orEmpty()
    ) {
        loadJob?.cancel()

        loadJob = viewLifecycleOwner.lifecycleScope.launch {
            binding.swipeRefresh.isRefreshing = true

            try {
                val response = FirebaseService.instance.getInventory(
                    session.getToken(),
                    search.takeIf { it.isNotBlank() }
                )

                val result = response.body()

                if (response.isSuccessful && result?.success == true) {
                    val items = result.data ?: emptyList()

                    adapter.submitList(items)

                    binding.tvEmpty.visibility =
                        if (items.isEmpty()) View.VISIBLE else View.GONE

                    binding.rvInventory.visibility =
                        if (items.isEmpty()) View.GONE else View.VISIBLE
                } else {
                    toast(result?.message ?: "Failed to load inventory")
                }
            } catch (e: Exception) {
                if (e !is kotlinx.coroutines.CancellationException) {
                    toast("Network error: ${e.localizedMessage ?: "Please try again"}")
                }
            } finally {
                if (_binding != null) {
                    binding.swipeRefresh.isRefreshing = false
                }
            }
        }
    }

    private fun showAddDialog() {
        val dialogBinding = DialogAddInventoryBinding.inflate(layoutInflater)

        // Pre-fill the low-stock alert level chosen in Settings.
        dialogBinding.etThreshold.setText(
            AppSettings(requireContext()).lowStockThreshold.toString()
        )

        val dialog = AlertDialog.Builder(requireContext())
            .setTitle("Add New Product")
            .setView(dialogBinding.root)
            .setPositiveButton("Add", null)
            .setNegativeButton("Cancel", null)
            .create()

        dialog.setOnShowListener {
            dialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener {
                val sku = dialogBinding.etSku.text.toString().trim()
                val name = dialogBinding.etProductName.text.toString().trim()
                val category = dialogBinding.etCategory.text.toString().trim()
                val quantityText = dialogBinding.etQuantity.text.toString().trim()
                val thresholdText = dialogBinding.etThreshold.text.toString().trim()
                val costText = dialogBinding.etCostPrice.text.toString().trim()
                val sellingText = dialogBinding.etSellingPrice.text.toString().trim()

                val quantity = quantityText.toIntOrNull()
                val threshold = thresholdText.toIntOrNull()
                val cost = costText.toDoubleOrNull()
                val selling = sellingText.toDoubleOrNull()

                if (sku.isBlank()) {
                    dialogBinding.etSku.error = "Product SKU is required"
                    return@setOnClickListener
                }

                if (name.isBlank()) {
                    dialogBinding.etProductName.error = "Product name is required"
                    return@setOnClickListener
                }

                if (quantity == null || quantity < 0) {
                    dialogBinding.etQuantity.error = "Enter a valid quantity"
                    return@setOnClickListener
                }

                if (threshold == null || threshold < 0) {
                    dialogBinding.etThreshold.error = "Enter a valid threshold"
                    return@setOnClickListener
                }

                if (cost == null || !cost.isFinite() || cost < 0) {
                    dialogBinding.etCostPrice.error = "Enter a valid cost price"
                    return@setOnClickListener
                }

                if (selling == null || !selling.isFinite() || selling < 0) {
                    dialogBinding.etSellingPrice.error = "Enter a valid selling price"
                    return@setOnClickListener
                }

                dialog.dismiss()

                addItem(
                    sku = sku,
                    name = name,
                    category = category,
                    quantity = quantity,
                    threshold = threshold,
                    cost = cost,
                    selling = selling
                )
            }
        }

        dialog.show()
    }

    private fun addItem(
        sku: String,
        name: String,
        category: String,
        quantity: Int,
        threshold: Int,
        cost: Double,
        selling: Double
    ) {
        viewLifecycleOwner.lifecycleScope.launch {
            try {
                val request = AddInventoryRequest(
                    sku = sku,
                    productName = name,
                    category = category,
                    quantity = quantity,
                    lowStockThreshold = threshold,
                    costPrice = cost,
                    sellingPrice = selling
                )

                val response = FirebaseService.instance.addInventoryItem(
                    session.getToken(),
                    request
                )

                val result = response.body()

                if (response.isSuccessful && result?.success == true) {
                    toast("Product added successfully")
                    loadInventory()
                } else {
                    toast(result?.message ?: "Failed to add product")
                }
            } catch (e: Exception) {
                if (e !is kotlinx.coroutines.CancellationException) {
                    toast("Network error: ${e.localizedMessage ?: "Please try again"}")
                }
            }
        }
    }

    private fun updateInventory(
        item: InventoryItem,
        quantity: Int,
        threshold: Int,
        cost: Double,
        selling: Double
    ) {
        viewLifecycleOwner.lifecycleScope.launch {
            try {
                val request = UpdateInventoryRequest(
                    id = item.id,
                    quantity = quantity,
                    lowStockThreshold = threshold,
                    costPrice = cost,
                    sellingPrice = selling
                )

                val response = FirebaseService.instance.updateInventoryItem(
                    session.getToken(),
                    request
                )

                val result = response.body()

                if (response.isSuccessful && result?.success == true) {
                    toast("Product updated successfully")
                    loadInventory()
                } else {
                    toast(result?.message ?: "Failed to update product")
                }
            } catch (e: Exception) {
                if (e !is kotlinx.coroutines.CancellationException) {
                    toast("Network error: ${e.localizedMessage ?: "Please try again"}")
                }
            }
        }
    }

    private fun confirmDelete(item: InventoryItem) {
        if (!AppSettings(requireContext()).confirmBeforeDelete) {
            deleteItem(item)
            return
        }

        AlertDialog.Builder(requireContext())
            .setTitle("Delete Product")
            .setMessage("Delete '${item.productName}'?")
            .setPositiveButton("Delete") { _, _ ->
                deleteItem(item)
            }
            .setNegativeButton("Cancel", null)
            .show()
    }

    private fun deleteItem(item: InventoryItem) {
        viewLifecycleOwner.lifecycleScope.launch {
            try {
                val response = FirebaseService.instance.deleteInventoryItem(
                    session.getToken(),
                    mapOf("id" to item.id)
                )

                val result = response.body()

                if (response.isSuccessful && result?.success == true) {
                    toast("Product deleted successfully")
                    loadInventory()
                } else {
                    toast(result?.message ?: "Failed to delete product")
                }
            } catch (e: Exception) {
                if (e !is kotlinx.coroutines.CancellationException) {
                    toast("Network error: ${e.localizedMessage ?: "Please try again"}")
                }
            }
        }
    }

    private fun toast(message: String) {
        context?.let {
            Toast.makeText(it, message, Toast.LENGTH_SHORT).show()
        }
    }

    override fun onDestroyView() {
        loadJob?.cancel()
        searchJob?.cancel()
        _binding = null
        super.onDestroyView()
    }
}