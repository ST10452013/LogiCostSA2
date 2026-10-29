package com.example.logicostapp3.ui.routes

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.fragment.app.Fragment
import com.example.logicostapp3.databinding.FragmentRoutesBinding

class RoutesFragment : Fragment() {

    private var _binding: FragmentRoutesBinding? = null
    private val binding get() = _binding!!

    // Demo route data matching the PHP route-map.php
    private val routes = listOf(
        RouteEntry("Truck #5 (GP 123-456)", "Thabo Nkosi",    "Johannesburg CBD", "Pretoria",   "45 min",  "🚚 In Transit"),
        RouteEntry("Van #3 (GP 789-012)",   "Sipho Dlamini",  "Midrand",          "Centurion",  "20 min",  "🚚 In Transit"),
        RouteEntry("Truck #2 (KZN 456-789)","Priya Naidoo",   "Durban Port",      "Pinetown",   "15 min",  "✅ Near Destination"),
        RouteEntry("Van #7 (WC 321-654)",   "James Mbeki",    "Cape Town",        "Stellenbosch","55 min", "⏳ Pending Pickup")
    )

    override fun onCreateView(
        inflater: LayoutInflater, container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentRoutesBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        val tableLayout = binding.tableRoutes
        routes.forEach { route ->
            val row = layoutInflater.inflate(
                com.example.logicostapp3.R.layout.item_route_row,
                tableLayout,
                false
            )
            row.findViewById<android.widget.TextView>(com.example.logicostapp3.R.id.tvVehicle).text      = route.vehicle
            row.findViewById<android.widget.TextView>(com.example.logicostapp3.R.id.tvDriver).text       = route.driver
            row.findViewById<android.widget.TextView>(com.example.logicostapp3.R.id.tvCurrentLoc).text   = route.currentLocation
            row.findViewById<android.widget.TextView>(com.example.logicostapp3.R.id.tvDestination).text  = route.destination
            row.findViewById<android.widget.TextView>(com.example.logicostapp3.R.id.tvEta).text          = route.eta
            row.findViewById<android.widget.TextView>(com.example.logicostapp3.R.id.tvRouteStatus).text  = route.status
            tableLayout.addView(row)
        }
    }

    data class RouteEntry(
        val vehicle: String,
        val driver: String,
        val currentLocation: String,
        val destination: String,
        val eta: String,
        val status: String
    )

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
