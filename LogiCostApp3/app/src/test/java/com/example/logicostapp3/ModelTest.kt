package com.example.logicostapp3

import com.example.logicostapp3.model.Delivery
import com.example.logicostapp3.model.InventoryItem
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class ModelTest {

    @Test
    fun item_isLowStock_whenAtOrBelowThreshold() {
        assertTrue(InventoryItem(quantity = 5, lowStockThreshold = 5).isLowStock)
        assertTrue(InventoryItem(quantity = 0, lowStockThreshold = 5).isLowStock)
        assertFalse(InventoryItem(quantity = 6, lowStockThreshold = 5).isLowStock)
    }

    @Test
    fun item_defaultThreshold_isFive() {
        assertEquals(5, InventoryItem().lowStockThreshold)
    }

    @Test
    fun delivery_statusLabels_areReadable() {
        assertEquals("Pending", Delivery(status = "pending").statusLabel)
        assertEquals("Assigned", Delivery(status = "assigned").statusLabel)
        assertEquals("In Transit", Delivery(status = "in_transit").statusLabel)
        assertEquals("Delivered", Delivery(status = "delivered").statusLabel)
        assertEquals("Cancelled", Delivery(status = "cancelled").statusLabel)
    }

    @Test
    fun delivery_unknownStatus_isShownAsIs() {
        assertEquals("on_hold", Delivery(status = "on_hold").statusLabel)
    }

    @Test
    fun delivery_newDelivery_isPending() {
        assertEquals("pending", Delivery().status)
    }
}
