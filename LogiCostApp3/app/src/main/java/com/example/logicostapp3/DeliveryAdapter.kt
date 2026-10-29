package com.example.logicostapp3.ui.deliveries

import android.graphics.Color
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.example.logicostapp3.databinding.ItemDeliveryBinding
import com.example.logicostapp3.model.Delivery

class DeliveryAdapter(
    private val onPrimary: (Delivery) -> Unit,
    private val onComplete: (Delivery) -> Unit,
    private val onCancel: (Delivery) -> Unit
) : ListAdapter<Delivery, DeliveryAdapter.ViewHolder>(DIFF) {

    companion object {
        private val DIFF = object : DiffUtil.ItemCallback<Delivery>() {
            override fun areItemsTheSame(
                oldItem: Delivery,
                newItem: Delivery
            ): Boolean = oldItem.id == newItem.id

            override fun areContentsTheSame(
                oldItem: Delivery,
                newItem: Delivery
            ): Boolean = oldItem == newItem
        }
    }

    inner class ViewHolder(
        private val b: ItemDeliveryBinding
    ) : RecyclerView.ViewHolder(b.root) {

        fun bind(d: Delivery) {
            b.tvOrderNumber.text = d.referenceCode
            b.tvDestination.text = d.dropoffAddress

            b.tvDriver.text = d.driverName
                ?.takeIf { it.isNotBlank() }
                ?.let { "Driver: $it" }
                ?: "Driver: Unassigned"

            b.tvStatus.text = "${d.statusEmoji} ${d.statusLabel}"

            val (background, stroke) = when (d.status) {
                "pending" ->
                    Color.parseColor("#FFF8E1") to
                            Color.parseColor("#FFD54F")

                "assigned" ->
                    Color.parseColor("#F3E5F5") to
                            Color.parseColor("#BA68C8")

                "in_transit" ->
                    Color.parseColor("#E3F2FD") to
                            Color.parseColor("#64B5F6")

                "delivered" ->
                    Color.parseColor("#E8F5E9") to
                            Color.parseColor("#81C784")

                else ->
                    Color.parseColor("#FFEBEE") to
                            Color.parseColor("#EF9A9A")
            }

            b.cardRoot.setCardBackgroundColor(background)
            b.cardRoot.strokeColor = stroke

            b.btnAssign.visibility = if (
                d.status == "pending" || d.status == "assigned"
            ) {
                View.VISIBLE
            } else {
                View.GONE
            }

            b.btnAssign.text = when (d.status) {
                "pending" -> "Assign driver"
                "assigned" -> "Start transit"
                else -> "Update"
            }

            b.btnComplete.visibility =
                if (d.status == "in_transit") {
                    View.VISIBLE
                } else {
                    View.GONE
                }

            b.btnComplete.text = "Mark delivered"

            b.btnAssign.setOnClickListener {
                onPrimary(d)
            }

            b.btnComplete.setOnClickListener {
                onComplete(d)
            }

            // The cancel button is optional in the layout.
            // It is connected below if your layout includes it.
            val cancelButton = b.root.findViewById<android.view.View>(
                b.root.context.resources.getIdentifier(
                    "btnCancel",
                    "id",
                    b.root.context.packageName
                )
            )

            if (cancelButton != null) {
                cancelButton.visibility =
                    if (d.status != "delivered" && d.status != "cancelled") {
                        View.VISIBLE
                    } else {
                        View.GONE
                    }

                cancelButton.setOnClickListener {
                    onCancel(d)
                }
            }
        }
    }

    override fun onCreateViewHolder(
        parent: ViewGroup,
        viewType: Int
    ): ViewHolder {
        val binding = ItemDeliveryBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return ViewHolder(binding)
    }

    override fun onBindViewHolder(
        holder: ViewHolder,
        position: Int
    ) {
        holder.bind(getItem(position))
    }
}

