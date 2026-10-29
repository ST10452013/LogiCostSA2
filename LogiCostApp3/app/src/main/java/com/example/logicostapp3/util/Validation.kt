package com.example.logicostapp3.util

/**
 * Input checks shared by the forms. Free of Android classes so they
 * can be unit tested.
 */
object Validation {

    private val emailRegex =
        Regex("^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$")

    // South African numbers: 0XXXXXXXXX or +27XXXXXXXXX (spaces allowed).
    private val phoneRegex = Regex("^(0|\\+27)[1-9][0-9]{8}$")

    const val MIN_PASSWORD_LENGTH = 8

    fun isValidEmail(email: String): Boolean =
        emailRegex.matches(email.trim())

    fun isValidPassword(password: String): Boolean =
        password.length >= MIN_PASSWORD_LENGTH &&
                password.any { it.isLetter() } &&
                password.any { it.isDigit() }

    fun isValidPhone(phone: String): Boolean =
        phoneRegex.matches(phone.replace(" ", ""))

    fun isValidName(name: String): Boolean =
        name.trim().length in 2..80

    /** A whole number of zero or more, or null. */
    fun parseQuantity(text: String): Int? =
        text.trim().toIntOrNull()?.takeIf { it >= 0 }

    /** A rand amount of zero or more ("12.50" or "12,50"), or null. */
    fun parseMoney(text: String): Double? =
        text.trim().replace(',', '.').toDoubleOrNull()
            ?.takeIf { it.isFinite() && it >= 0 }
}
