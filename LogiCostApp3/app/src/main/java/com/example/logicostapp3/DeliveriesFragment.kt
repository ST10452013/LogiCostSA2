package com.example.logicostapp3.ui.deliveries

import android.app.DatePickerDialog
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.Toast
import androidx.appcompat.app.AlertDialog
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import com.example.logicostapp3.MainActivity
import com.example.logicostapp3.api.FirebaseService
import com.example.logicostapp3.api.SessionManager
import com.example.logicostapp3.databinding.DialogAddDeliveryBinding
import com.example.logicostapp3.databinding.FragmentDeliveriesBinding
import com.example.logicostapp3.model.AddDeliveryRequest
import com.example.logicostapp3.model.Delivery
import com.example.logicostapp3.model.UpdateDeliveryRequest
import kotlinx.coroutines.launch
import java.util.Calendar
import java.util.Locale

class DeliveriesFragment : Fragment() {

    private var _binding: FragmentDeliveriesBinding? = null
    private val binding get() = _binding!!

    private lateinit var session: SessionManager
    private lateinit var adapter: DeliveryAdapter

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentDeliveriesBinding.inflate(
            inflater, container, false
        )
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        session = SessionManager(requireContext())

        adapter = DeliveryAdapter(
            onPrimary = { delivery -> advanceDelivery(delivery) },
            onComplete = { delivery -> confirmDelivered(delivery) },
            onCancel = { delivery -> confirmCancel(delivery) }
        )

        binding.rvDeliveries.layoutManager =
            LinearLayoutManager(requireContext())
        binding.rvDeliveries.adapter = adapter

        binding.fabAdd.setOnClickListener { showAddDialog() }
        binding.swipeRefresh.setOnRefreshListener { loadDeliveries() }

        loadDeliveries()
    }

    private fun loadDeliveries() {
        binding.swipeRefresh.isRefreshing = true

        viewLifecycleOwner.lifecycleScope.launch {
            try {
                val response = FirebaseService.instance.getDeliveries(
                    session.getToken()
                )

                if (response.isSuccessful &&
                    response.body()?.success == true
                ) {
                    val deliveries = response.body()?.data.orEmpty()
                    adapter.submitList(deliveries)
                    binding.tvEmpty.visibility =
                        if (deliveries.isEmpty()) View.VISIBLE else View.GONE
                } else {
                    handleFailure(
                        response.code(),
                        response.body()?.message ?: "Failed to load deliveries"
                    )
                }
            } catch (e: Exception) {
                toast("Network error: ${e.localizedMessage ?: "Please try again"}")
            } finally {
                binding.swipeRefresh.isRefreshing = false
            }
        }
    }

    private fun showAddDialog() {
        val dialogBinding = DialogAddDeliveryBinding.inflate(layoutInflater)

        dialogBinding.etScheduledDate.setOnClickListener {
            val calendar = Calendar.getInstance()

            DatePickerDialog(
                requireContext(),
                { _, year, month, day ->
                    dialogBinding.etScheduledDate.setText(
                        String.format(
                            Locale.US,
                            "%04d-%02d-%02d",
                            year,
                            month + 1,
                            day
                        )
                    )
                },
                calendar.get(Calendar.YEAR),
                calendar.get(Calendar.MONTH),
                calendar.get(Calendar.DAY_OF_MONTH)
            ).show()
        }

        val dialog = AlertDialog.Builder(requireContext())
            .setTitle("Create New Delivery")
            .setView(dialogBinding.root)
            .setPositiveButton("Create", null)
            .setNegativeButton("Cancel", null)
            .create()

        dialog.setOnShowListener {
            dialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener {
                val reference =
                    dialogBinding.etReferenceCode.text.toString().trim()
                val pickup =
                    dialogBinding.etPickupAddress.text.toString().trim()
                val dropoff =
                    dialogBinding.etDropoffAddress.text.toString().trim()
                val packageDescription =
                    dialogBinding.etPackageDescription.text.toString().trim()
                val scheduled =
                    dialogBinding.etScheduledDate.text.toString().trim()
                val cost =
                    dialogBinding.etAgreedCost.text.toString().trim()

                if (reference.isBlank() || pickup.isBlank() ||
                    dropoff.isBlank() || packageDescription.isBlank()
                ) {
                    toast("Fill in all required fields")
                    return@setOnClickListener
                }

                if (scheduled.isNotEmpty() &&
                    !scheduled.matches(Regex("\\d{4}-\\d{2}-\\d{2}"))
                ) {
                    toast("Use the date format YYYY-MM-DD")
                    return@setOnClickListener
                }

                if (cost.isNotEmpty() &&
                    (cost.toDoubleOrNull() == null ||
                            cost.toDoubleOrNull()!! < 0)
                ) {
                    toast("Enter a valid delivery cost")
                    return@setOnClickListener
                }

                val request = AddDeliveryRequest(
                    referenceCode = reference,
                    pickupAddress = pickup,
                    dropoffAddress = dropoff,
                    packageDescription = packageDescription,
                    recipientName = dialogBinding.etRecipientName.text
                        .toString().trim(),
                    recipientPhone = dialogBinding.etRecipientPhone.text
                        .toString().trim(),
                    scheduledDate = scheduled,
                    agreedCost = cost
                )

                dialog.dismiss()
                addDelivery(request)
            }
        }

        dialog.show()
    }

    private fun addDelivery(request: AddDeliveryRequest) {
        viewLifecycleOwner.lifecycleScope.launch {
            try {
                val response = FirebaseService.instance.addDelivery(
                    session.getToken(),
                    request
                )

                if (response.isSuccessful &&
                    response.body()?.success == true
                ) {
                    toast("Delivery created successfully")
                    loadDeliveries()
                } else {
                    handleFailure(
                        response.code(),
                        response.body()?.message ?: "Failed to create delivery"
                    )
                }
            } catch (e: Exception) {
                toast("Network error: ${e.localizedMessage ?: "Please try again"}")
            }
        }
    }

    private fun advanceDelivery(delivery: Delivery) {
        when (delivery.status) {
            "pending" -> showAssignDriverDialog(delivery)

            "assigned" -> confirmStatusUpdate(
                delivery,
                "in_transit",
                "Start transit",
                "Start transit for ${delivery.referenceCode}?"
            )

            else -> toast("This delivery cannot be advanced from its current status")
        }
    }

    private fun showAssignDriverDialog(delivery: Delivery) {
        val padding = (16 * resources.displayMetrics.density).toInt()

        val container = LinearLayout(requireContext()).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(padding, 0, padding, 0)
        }

        val nameInput = EditText(requireContext()).apply {
            hint = "Driver name"
            setSingleLine(true)
            setText(delivery.driverName.orEmpty())
        }

        val phoneInput = EditText(requireContext()).apply {
            hint = "Driver phone"
            inputType = android.text.InputType.TYPE_CLASS_PHONE
            setSingleLine(true)
            setText(delivery.driverPhone.orEmpty())
        }

        container.addView(nameInput)
        container.addView(phoneInput)

        val dialog = AlertDialog.Builder(requireContext())
            .setTitle("Assign driver")
            .setMessage("Delivery: ${delivery.referenceCode}")
            .setView(container)
            .setPositiveButton("Assign", null)
            .setNegativeButton("Cancel", null)
            .create()

        dialog.setOnShowListener {
            dialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener {
                val name = nameInput.text.toString().trim()
                val phone = phoneInput.text.toString().trim()

                if (name.isBlank() || phone.isBlank()) {
                    toast("Enter both the driver name and phone number")
                    return@setOnClickListener
                }

                dialog.dismiss()

                updateDelivery(
                    UpdateDeliveryRequest(
                        id = delivery.id,
                        status = "assigned",
                        driverName = name,
                        driverPhone = phone,
                        note = "Driver assigned"
                    )
                )
            }
        }

        dialog.show()
    }

    private fun confirmDelivered(delivery: Delivery) {
        confirmStatusUpdate(
            delivery,
            "delivered",
            "Mark delivered",
            "Mark ${delivery.referenceCode} as delivered?"
        )
    }

    private fun confirmCancel(delivery: Delivery) {
        AlertDialog.Builder(requireContext())
            .setTitle("Cancel delivery")
            .setMessage("Cancel ${delivery.referenceCode}?")
            .setPositiveButton("Yes, cancel") { _, _ ->
                updateDelivery(
                    UpdateDeliveryRequest(
                        id = delivery.id,
                        status = "cancelled",
                        note = "Cancelled from the Android app"
                    )
                )
            }
            .setNegativeButton("Keep delivery", null)
            .show()
    }

    private fun confirmStatusUpdate(
        delivery: Delivery,
        newStatus: String,
        title: String,
        message: String
    ) {
        AlertDialog.Builder(requireContext())
            .setTitle(title)
            .setMessage(message)
            .setPositiveButton("Yes") { _, _ ->
                updateDelivery(
                    UpdateDeliveryRequest(
                        id = delivery.id,
                        status = newStatus,
                        note = when (newStatus) {
                            "in_transit" -> "Delivery started"
                            "delivered" -> "Marked as delivered"
                            else -> ""
                        }
                    )
                )
            }
            .setNegativeButton("Cancel", null)
            .show()
    }

    private fun updateDelivery(request: UpdateDeliveryRequest) {
        viewLifecycleOwner.lifecycleScope.launch {
            try {
                val response = FirebaseService.instance.updateDelivery(
                    session.getToken(),
                    request
                )

                if (response.isSuccessful &&
                    response.body()?.success == true
                ) {
                    toast(response.body()?.message ?: "Delivery updated")
                    loadDeliveries()
                } else {
                    handleFailure(
                        response.code(),
                        response.body()?.message ?: "Failed to update delivery"
                    )
                }
            } catch (e: Exception) {
                toast("Network error: ${e.localizedMessage ?: "Please try again"}")
            }
        }
    }

    private fun handleFailure(code: Int, message: String) {
        if (code == 401 && isAdded) {
            toast("Your session has expired. Please log in again.")
            (activity as? MainActivity)?.logout()
        } else {
            toast(message)
        }
    }

    private fun toast(message: String) {
        Toast.makeText(requireContext(), message, Toast.LENGTH_SHORT).show()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
