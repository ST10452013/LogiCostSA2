package com.example.logicostapp3.api

import com.google.firebase.firestore.FirebaseFirestore
import kotlinx.coroutines.tasks.await
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale
import kotlin.random.Random

/**
 * Sample records written for every new business, so the app
 * shows realistic data straight after registration
 * (at least ten records per collection).
 */
object SampleData {

    private data class Product(
        val sku: String,
        val name: String,
        val category: String,
        val quantity: Int,
        val threshold: Int,
        val cost: Double,
        val price: Double
    )

    private data class Job(
        val pickup: String,
        val dropoff: String,
        val description: String,
        val recipient: String,
        val phone: String,
        val cost: Double,
        val status: String,
        val driver: String?
    )

    private val products = listOf(
        Product("STL-001", "Mild Steel Sheet 2mm", "Raw Materials", 120, 20, 450.00, 620.00),
        Product("STL-002", "Galvanised Steel Pipe 50mm", "Raw Materials", 18, 25, 310.00, 425.00),
        Product("BLT-010", "Hex Bolt M12 (Box of 100)", "Fasteners", 64, 15, 185.00, 260.00),
        Product("WLD-004", "Welding Rods 3.2mm (5kg)", "Consumables", 9, 10, 290.00, 395.00),
        Product("PNT-021", "Industrial Primer 20L", "Coatings", 32, 8, 1150.00, 1490.00),
        Product("PKG-100", "Cardboard Box Large", "Packaging", 540, 100, 14.50, 22.00),
        Product("PKG-101", "Pallet Wrap 500mm", "Packaging", 45, 12, 98.00, 135.00),
        Product("ELC-007", "3-Phase Motor 5.5kW", "Equipment", 4, 2, 7800.00, 9950.00),
        Product("SAF-030", "Safety Boots (Size 9)", "Safety", 26, 10, 420.00, 560.00),
        Product("SAF-031", "Hi-Vis Vest Reflective", "Safety", 3, 15, 45.00, 75.00)
    )

    private val jobs = listOf(
        Job("Midrand Industrial Park", "Pretoria West Depot", "12 steel sheets", "Thabo Nkosi", "0821234567", 1450.00, "delivered", "Sipho Dlamini"),
        Job("Germiston Factory", "Durban Harbour Gate 3", "2 pallets of fasteners", "Ayesha Patel", "0839876543", 6800.00, "in_transit", "Johan Botha"),
        Job("Kempton Park Warehouse", "Polokwane Retail Store", "Industrial primer x 10", "Lerato Mokoena", "0724567890", 4200.00, "assigned", "Neo Molefe"),
        Job("Cape Town Paarden Eiland", "Stellenbosch Workshop", "Welding consumables", "Pieter van Wyk", "0612345678", 950.00, "pending", null),
        Job("Isando Distribution Centre", "Rustenburg Mine Supply", "Safety boots x 40", "Kagiso Sithole", "0798765432", 3100.00, "delivered", "Sipho Dlamini"),
        Job("Port Elizabeth Plant", "East London Depot", "3-phase motor", "Zanele Mthembu", "0745678901", 2750.00, "pending", null),
        Job("Benoni Workshop", "Springs Assembly Line", "Galvanised pipes x 30", "Ruan Pretorius", "0823456789", 880.00, "cancelled", null),
        Job("Bloemfontein Hub", "Kimberley Branch", "Packaging materials", "Mpho Radebe", "0712345098", 2300.00, "in_transit", "Johan Botha"),
        Job("Pinetown Factory", "Pietermaritzburg Store", "Pallet wrap x 25", "Nomsa Zulu", "0834567123", 1200.00, "delivered", "Neo Molefe"),
        Job("Alberton Steel Yard", "Vereeniging Plant", "Mild steel sheets x 60", "David Mahlangu", "0765432109", 1900.00, "assigned", "Sipho Dlamini")
    )

    suspend fun seedCompany(db: FirebaseFirestore, companyId: Int) {
        val batch = db.batch()
        val now = format(Calendar.getInstance())

        products.forEachIndexed { index, p ->
            val id = Random.nextInt(100_000, Int.MAX_VALUE)
            batch.set(
                db.collection("inventory").document(id.toString()),
                mapOf(
                    "id" to id,
                    "companyId" to companyId,
                    "sku" to p.sku,
                    "productName" to p.name,
                    "category" to p.category,
                    "quantity" to p.quantity,
                    "lowStockThreshold" to p.threshold,
                    "costPrice" to p.cost,
                    "sellingPrice" to p.price,
                    "createdAt" to now,
                    "updatedAt" to now
                )
            )
        }

        jobs.forEachIndexed { index, j ->
            val id = Random.nextInt(100_000, Int.MAX_VALUE)
            val created = Calendar.getInstance().apply { add(Calendar.DAY_OF_MONTH, -(10 - index)) }
            val scheduled = Calendar.getInstance().apply { add(Calendar.DAY_OF_MONTH, index - 4) }

            batch.set(
                db.collection("deliveries").document(id.toString()),
                mapOf(
                    "id" to id,
                    "companyId" to companyId,
                    "referenceCode" to "LC-%04d".format(1001 + index),
                    "pickupAddress" to j.pickup,
                    "dropoffAddress" to j.dropoff,
                    "packageDescription" to j.description,
                    "recipientName" to j.recipient,
                    "recipientPhone" to j.phone,
                    "scheduledDate" to format(scheduled).substring(0, 10),
                    "agreedCost" to j.cost,
                    "driverName" to j.driver,
                    "driverPhone" to j.driver?.let { "0800000${100 + index}" },
                    "status" to j.status,
                    "createdAt" to format(created),
                    "updatedAt" to now
                )
            )
        }

        batch.commit().await()
    }

    private fun format(calendar: Calendar): String =
        SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.US).format(calendar.time)
}
