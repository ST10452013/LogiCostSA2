package com.example.logicostapp3

import com.example.logicostapp3.util.Validation
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Test

class ValidationTest {

    @Test
    fun validEmails_areAccepted() {
        assertTrue(Validation.isValidEmail("owner@logicost.co.za"))
        assertTrue(Validation.isValidEmail("thabo.nkosi+test@gmail.com"))
        assertTrue(Validation.isValidEmail("  padded@example.com  "))
    }

    @Test
    fun invalidEmails_areRejected() {
        assertFalse(Validation.isValidEmail(""))
        assertFalse(Validation.isValidEmail("no-at-sign.co.za"))
        assertFalse(Validation.isValidEmail("missing@domain"))
        assertFalse(Validation.isValidEmail("two@@signs.com"))
        assertFalse(Validation.isValidEmail("spaces in@email.com"))
    }

    @Test
    fun strongPasswords_areAccepted() {
        assertTrue(Validation.isValidPassword("Logicost2026"))
        assertTrue(Validation.isValidPassword("abcdefg1"))
    }

    @Test
    fun weakPasswords_areRejected() {
        assertFalse(Validation.isValidPassword("short1"))      // too short
        assertFalse(Validation.isValidPassword("onlyletters")) // no digit
        assertFalse(Validation.isValidPassword("12345678"))    // no letter
    }

    @Test
    fun southAfricanPhoneNumbers_areAccepted() {
        assertTrue(Validation.isValidPhone("0821234567"))
        assertTrue(Validation.isValidPhone("082 123 4567"))
        assertTrue(Validation.isValidPhone("+27821234567"))
        assertTrue(Validation.isValidPhone("0114567890"))
    }

    @Test
    fun invalidPhoneNumbers_areRejected() {
        assertFalse(Validation.isValidPhone(""))
        assertFalse(Validation.isValidPhone("082123456"))    // too short
        assertFalse(Validation.isValidPhone("08212345678"))  // too long
        assertFalse(Validation.isValidPhone("0021234567"))   // invalid area code
        assertFalse(Validation.isValidPhone("phone"))
    }

    @Test
    fun names_mustBeTwoToEightyCharacters() {
        assertTrue(Validation.isValidName("Al"))
        assertTrue(Validation.isValidName("Lethabo Mdhluli"))
        assertFalse(Validation.isValidName("A"))
        assertFalse(Validation.isValidName("   "))
        assertFalse(Validation.isValidName("x".repeat(81)))
    }

    @Test
    fun quantities_mustBeWholeNumbersOfZeroOrMore() {
        assertEquals(0, Validation.parseQuantity("0"))
        assertEquals(120, Validation.parseQuantity(" 120 "))
        assertNull(Validation.parseQuantity("-1"))
        assertNull(Validation.parseQuantity("2.5"))
        assertNull(Validation.parseQuantity("ten"))
    }

    @Test
    fun money_acceptsRandAmountsOfZeroOrMore() {
        assertEquals(450.0, Validation.parseMoney("450")!!, 0.001)
        assertEquals(14.5, Validation.parseMoney("14,50")!!, 0.001)
        assertNull(Validation.parseMoney("-1"))
        assertNull(Validation.parseMoney("R100"))
    }
}
