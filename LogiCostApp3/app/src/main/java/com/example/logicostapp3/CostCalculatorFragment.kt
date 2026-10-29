
package com.example.logicostapp3.ui.calculator

import android.os.Bundle
import android.view.View
import android.widget.EditText
import android.widget.TextView
import android.widget.Toast
import androidx.fragment.app.Fragment
import com.example.logicostapp3.R
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

        calculateButton.setOnClickListener {
            // The three main inputs are required.
            val km = distance.text.toString().toDoubleOrNull()
            val litresPer100Km =
                consumption.text.toString().toDoubleOrNull()
            val pricePerLitre =
                fuelPrice.text.toString().toDoubleOrNull()

            if (km == null || litresPer100Km == null ||
                pricePerLitre == null ||
                km <= 0 || litresPer100Km < 0 || pricePerLitre < 0
            ) {
                Toast.makeText(
                    requireContext(),
                    "Enter a valid distance, fuel consumption and fuel price.",
                    Toast.LENGTH_LONG
                ).show()
                return@setOnClickListener
            }

            // Blank additional expense fields are treated as zero.
            fun expense(input: EditText): Double? {
                val text = input.text.toString().trim()
                val amount = if (text.isEmpty()) 0.0
                else text.toDoubleOrNull()

                return amount?.takeIf {
                    it.isFinite() && it >= 0
                }
            }

            val toll = expense(tollFees)
            val wages = expense(driverWages)
            val other = expense(otherExpenses)

            if (toll == null || wages == null || other == null) {
                Toast.makeText(
                    requireContext(),
                    "Enter valid, non-negative additional expenses.",
                    Toast.LENGTH_LONG
                ).show()
                return@setOnClickListener
            }

            // Fuel used = distance × consumption / 100.
            val fuelUsed = km * litresPer100Km / 100.0
            val fuelCost = fuelUsed * pricePerLitre

            val totalCost = fuelCost + toll + wages + other
            val costPerKm = totalCost / km

            // Display the results in South African rand.
            fun money(amount: Double): String =
                String.format(Locale.getDefault(), "R %.2f", amount)

            fuelResult.text = money(fuelCost)
            totalResult.text = money(totalCost)
            perKmResult.text = "${money(costPerKm)} / km"
        }

        clearButton.setOnClickListener {
            distance.text.clear()
            consumption.text.clear()
            fuelPrice.text.clear()
            tollFees.setText("0")
            driverWages.setText("0")
            otherExpenses.setText("0")

            fuelResult.text = "R 0.00"
            totalResult.text = "R 0.00"
            perKmResult.text = "R 0.00 / km"
        }
    }
}

