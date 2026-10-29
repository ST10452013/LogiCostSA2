
package com.example.logicostapp3.ui.calculator

import android.os.Bundle
import android.view.View
import android.widget.EditText
import android.widget.TextView
import android.widget.Toast
import androidx.fragment.app.Fragment
import com.example.logicostapp3.R
import com.example.logicostapp3.api.AppSettings
import com.example.logicostapp3.util.CostCalculator
import com.google.android.material.button.MaterialButton
import java.util.Locale

class CostCalculatorFragment :
    Fragment(R.layout.fragment_cost_calculator) {

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        val distance = view.findViewById<EditText>(R.id.etDistance)
        val consumption = view.findViewById<EditText>(R.id.etFuelConsumption)
        val fuelPrice = view.findViewById<EditText>(R.id.etFuelPrice)
        val tollFees = view.findViewById<EditText>(R.id.etTollFees)
        val driverWages = view.findViewById<EditText>(R.id.etDriverWages)
        val otherExpenses = view.findViewById<EditText>(R.id.etOtherExpenses)

        val fuelResult = view.findViewById<TextView>(R.id.tvFuelCost)
        val totalResult = view.findViewById<TextView>(R.id.tvTotalCost)
        val perKmResult = view.findViewById<TextView>(R.id.tvCostPerKm)

        val calculateButton =
            view.findViewById<MaterialButton>(R.id.btnCalculate)
        val clearButton =
            view.findViewById<MaterialButton>(R.id.btnClear)

        // Pre-fill the vehicle defaults chosen in Settings.
        val settings = AppSettings(requireContext())
        fun fillDefaults() {
            consumption.setText(settings.fuelConsumption.toString())
            fuelPrice.setText(settings.fuelPrice.toString())
        }
        fillDefaults()

        calculateButton.setOnClickListener {
            val km = distance.text.toString().trim().replace(',', '.').toDoubleOrNull()
            val litresPer100Km = consumption.text.toString().trim().replace(',', '.').toDoubleOrNull()
            val pricePerLitre = fuelPrice.text.toString().trim().replace(',', '.').toDoubleOrNull()

            // The three main inputs are required.
            if (km == null || litresPer100Km == null || pricePerLitre == null) {
                Toast.makeText(
                    requireContext(),
                    "Enter a valid distance, fuel consumption and fuel price.",
                    Toast.LENGTH_LONG
                ).show()
                return@setOnClickListener
            }

            // Blank additional expense fields are treated as zero.
            val toll = CostCalculator.parseExpense(tollFees.text.toString())
            val wages = CostCalculator.parseExpense(driverWages.text.toString())
            val other = CostCalculator.parseExpense(otherExpenses.text.toString())

            if (toll == null || wages == null || other == null) {
                Toast.makeText(
                    requireContext(),
                    "Enter valid, non-negative additional expenses.",
                    Toast.LENGTH_LONG
                ).show()
                return@setOnClickListener
            }

            val result = CostCalculator.calculate(
                km, litresPer100Km, pricePerLitre, toll, wages, other
            )

            if (result == null) {
                Toast.makeText(
                    requireContext(),
                    "Distance must be above zero and costs cannot be negative.",
                    Toast.LENGTH_LONG
                ).show()
                return@setOnClickListener
            }

            // Display the results in South African rand.
            fun money(amount: Double): String =
                String.format(Locale.getDefault(), "R %.2f", amount)

            fuelResult.text = money(result.fuelCost)
            totalResult.text = money(result.totalCost)
            perKmResult.text = "${money(result.costPerKm)} / km"
        }

        clearButton.setOnClickListener {
            distance.text.clear()
            fillDefaults()
            tollFees.setText("0")
            driverWages.setText("0")
            otherExpenses.setText("0")

            fuelResult.text = "R 0.00"
            totalResult.text = "R 0.00"
            perKmResult.text = "R 0.00 / km"
        }
    }
}

