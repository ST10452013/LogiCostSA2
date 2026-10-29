package com.example.logicostapp3

import com.example.logicostapp3.util.CostCalculator
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNotNull
import org.junit.Assert.assertNull
import org.junit.Test

class CostCalculatorTest {

    private val delta = 0.001

    @Test
    fun fuelCost_isDistanceTimesConsumptionTimesPrice() {
        // 200 km at 10 L/100 km = 20 L; 20 L x R25 = R500
        val result = CostCalculator.calculate(200.0, 10.0, 25.0)!!

        assertEquals(20.0, result.fuelLitres, delta)
        assertEquals(500.0, result.fuelCost, delta)
        assertEquals(500.0, result.totalCost, delta)
        assertEquals(2.5, result.costPerKm, delta)
    }

    @Test
    fun totalCost_includesTollsWagesAndOtherExpenses() {
        val result = CostCalculator.calculate(
            distanceKm = 100.0,
            litresPer100Km = 12.0,
            pricePerLitre = 23.5,
            tollFees = 85.0,
            driverWages = 450.0,
            otherExpenses = 65.0
        )!!

        // Fuel: 12 L x R23.50 = R282
        assertEquals(282.0, result.fuelCost, delta)
        assertEquals(882.0, result.totalCost, delta)
        assertEquals(8.82, result.costPerKm, delta)
    }

    @Test
    fun zeroOrNegativeDistance_isRejected() {
        assertNull(CostCalculator.calculate(0.0, 10.0, 25.0))
        assertNull(CostCalculator.calculate(-50.0, 10.0, 25.0))
    }

    @Test
    fun negativeCosts_areRejected() {
        assertNull(CostCalculator.calculate(100.0, -1.0, 25.0))
        assertNull(CostCalculator.calculate(100.0, 10.0, -25.0))
        assertNull(CostCalculator.calculate(100.0, 10.0, 25.0, tollFees = -5.0))
        assertNull(CostCalculator.calculate(100.0, 10.0, 25.0, driverWages = -1.0))
        assertNull(CostCalculator.calculate(100.0, 10.0, 25.0, otherExpenses = -0.01))
    }

    @Test
    fun infiniteOrNotANumber_isRejected() {
        assertNull(CostCalculator.calculate(Double.NaN, 10.0, 25.0))
        assertNull(CostCalculator.calculate(100.0, Double.POSITIVE_INFINITY, 25.0))
    }

    @Test
    fun freeFuel_givesZeroFuelCost() {
        val result = CostCalculator.calculate(100.0, 0.0, 25.0, tollFees = 50.0)
        assertNotNull(result)
        assertEquals(0.0, result!!.fuelCost, delta)
        assertEquals(50.0, result.totalCost, delta)
    }

    @Test
    fun parseExpense_treatsBlankAsZero() {
        assertEquals(0.0, CostCalculator.parseExpense("")!!, delta)
        assertEquals(0.0, CostCalculator.parseExpense("   ")!!, delta)
    }

    @Test
    fun parseExpense_acceptsDecimalPointOrComma() {
        assertEquals(12.5, CostCalculator.parseExpense("12.50")!!, delta)
        assertEquals(12.5, CostCalculator.parseExpense("12,50")!!, delta)
    }

    @Test
    fun parseExpense_rejectsTextAndNegatives() {
        assertNull(CostCalculator.parseExpense("abc"))
        assertNull(CostCalculator.parseExpense("-10"))
    }
}
