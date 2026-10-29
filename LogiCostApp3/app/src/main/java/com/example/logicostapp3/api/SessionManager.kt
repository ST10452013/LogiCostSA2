package com.example.logicostapp3.api

import android.content.Context
import android.content.SharedPreferences

class SessionManager(context: Context) {

    private val prefs: SharedPreferences =
        context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE)

    companion object {
        private const val PREF_NAME = "logicost_session"
        private const val KEY_TOKEN = "token"
        private const val KEY_USER_ID = "user_id"
        private const val KEY_NAME = "user_name"
        private const val KEY_EMAIL = "user_email"
        private const val KEY_ROLE = "user_role"
        private const val KEY_LOGGED = "is_logged_in"
    }

    fun saveSession(
        token: String,
        id: Int,
        name: String,
        email: String,
        role: String
    ) {
        prefs.edit().apply {
            putString(KEY_TOKEN, token)
            putInt(KEY_USER_ID, id)
            putString(KEY_NAME, name)
            putString(KEY_EMAIL, email)
            putString(KEY_ROLE, role)
            putBoolean(KEY_LOGGED, true)
            apply()
        }
    }

    // Set login status manually for development testing
    fun setLoggedIn(value: Boolean) {
        prefs.edit()
            .putBoolean(KEY_LOGGED, value)
            .apply()
    }

    fun getToken(): String =
        "Bearer ${prefs.getString(KEY_TOKEN, "") ?: ""}"

    fun getUserId(): Int = prefs.getInt(KEY_USER_ID, 0)

    fun getUserName(): String =
        prefs.getString(KEY_NAME, "") ?: ""

    fun getUserEmail(): String =
        prefs.getString(KEY_EMAIL, "") ?: ""

    fun getUserRole(): String =
        prefs.getString(KEY_ROLE, "") ?: ""

    fun isLoggedIn(): Boolean =
        prefs.getBoolean(KEY_LOGGED, false)

    fun logout() {
        prefs.edit().clear().apply()
    }

    //backdoor code
    fun loginAsGuest() {
        prefs.edit().apply {
            putBoolean(KEY_LOGGED, true)
            putString(KEY_TOKEN, "DEV_GUEST_TOKEN")
            putInt(KEY_USER_ID, 1)
            putString(KEY_NAME, "Guest User")
            putString(KEY_EMAIL, "guest@logicost.local")
            putString(KEY_ROLE, "Developer")
            apply()
        }
    }
}

