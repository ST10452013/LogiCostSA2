package com.example.logicostapp3.util

/**
 * Delivery cost calculation used by the Cost Calculator screen.
 * Kept free of Android classes so it can be unit tested.
 */
object CostCalculator {

    data class Result(
        val fuelLitres: Double,
        val fuelCost: Double,
        val totalCost: Double,
        val costPerKm: Double
    )

    /**
     * Returns null when the inputs are not valid:
     * distance must be above zero and every other value zero or more.
     */
    fun calculate(
        distanceKm: Double,
        litresPer100Km: Double,
        pricePerLitre: Double,
        tollFees: Double = 0.0,
        driverWages: Double = 0.0,
        otherExpenses: Double = 0.0
    ): Result? {
        val costs = listOf(litresPer100Km, pricePerLitre, tollFees, driverWages, otherExpenses)

        if (!distanceKm.isFinite() || distanceKm <= 0) return null
        if (costs.any { !it.isFinite() || it < 0 }) return null

        // Fuel used = distance × consumption / 100.
        val fuelLitres = distanceKm * litresPer100Km / 100.0
        val fuelCost = fuelLitres * pricePerLitre
        val totalCost = fuelCost + tollFees + driverWages + otherExpenses

        return Result(
            fuelLitres = fuelLitres,
            fuelCost = fuelCost,
            totalCost = totalCost,
            costPerKm = totalCost / distanceKm
        )
    }

    /**
     * Reads an optional expense field: blank means zero,
     * anything that is not a non-negative number is invalid (null).
     */
    fun parseExpense(text: String): Double? {
        val trimmed = text.trim()
        val amount = if (trimmed.isEmpty()) 0.0 else trimmed.replace(',', '.').toDoubleOrNull()
        return amount?.takeIf { it.isFinite() && it >= 0 }
    }
}
