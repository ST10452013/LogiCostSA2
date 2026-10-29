package com.example.logicostapp3.api

import android.content.Context
import android.content.SharedPreferences

/**
 * Preferences the user can change on the Settings screen.
 * Stored on the device and kept when the user logs out.
 */
class AppSettings(context: Context) {

    private val prefs: SharedPreferences =
        context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE)

    companion object {
        private const val PREF_NAME = "logicost_settings"
        private const val KEY_FUEL_CONSUMPTION = "fuel_consumption"
        private const val KEY_FUEL_PRICE = "fuel_price"
        private const val KEY_LOW_STOCK = "low_stock_threshold"
        private const val KEY_CONFIRM_DELETE = "confirm_delete"

        const val DEFAULT_FUEL_CONSUMPTION = 12.0
        const val DEFAULT_FUEL_PRICE = 23.50
        const val DEFAULT_LOW_STOCK = 5
    }

    var fuelConsumption: Double
        get() = prefs.getFloat(KEY_FUEL_CONSUMPTION, DEFAULT_FUEL_CONSUMPTION.toFloat()).toDouble()
        set(value) = prefs.edit().putFloat(KEY_FUEL_CONSUMPTION, value.toFloat()).apply()

    var fuelPrice: Double
        get() = prefs.getFloat(KEY_FUEL_PRICE, DEFAULT_FUEL_PRICE.toFloat()).toDouble()
        set(value) = prefs.edit().putFloat(KEY_FUEL_PRICE, value.toFloat()).apply()

    var lowStockThreshold: Int
        get() = prefs.getInt(KEY_LOW_STOCK, DEFAULT_LOW_STOCK)
        set(value) = prefs.edit().putInt(KEY_LOW_STOCK, value).apply()

    var confirmBeforeDelete: Boolean
        get() = prefs.getBoolean(KEY_CONFIRM_DELETE, true)
        set(value) = prefs.edit().putBoolean(KEY_CONFIRM_DELETE, value).apply()
}
