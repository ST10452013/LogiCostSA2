package com.example.logicostapp3.ui.register

import android.os.Bundle
import android.view.View
import android.widget.ArrayAdapter
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.example.logicostapp3.api.RetrofitClient
import com.example.logicostapp3.databinding.ActivityRegisterBinding
import com.example.logicostapp3.model.RegisterRequest
import kotlinx.coroutines.launch
import org.json.JSONObject

class RegisterActivity : AppCompatActivity() {

    private lateinit var binding: ActivityRegisterBinding

    private val businessTypes = arrayOf(
        "small_business",
        "manufacturer",
        "supplier",
        "driver",
        "transport_company",
        "warehouse",
        "retailer",
        "other"
    )

    private val businessTypeLabels = arrayOf(
        "Small business",
        "Manufacturer",
        "Supplier",
        "Independent driver",
        "Transport company",
        "Warehouse",
        "Retailer",
        "Other"
    )

    private val provinces = arrayOf(
        "",
        "Eastern Cape",
        "Free State",
        "Gauteng",
        "KwaZulu-Natal",
        "Limpopo",
        "Mpumalanga",
        "Northern Cape",
        "North West",
        "Western Cape"
    )

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        binding = ActivityRegisterBinding.inflate(layoutInflater)
        setContentView(binding.root)

        val businessAdapter = ArrayAdapter(
            this,
            android.R.layout.simple_spinner_item,
            businessTypeLabels
        )
        businessAdapter.setDropDownViewResource(
            android.R.layout.simple_spinner_dropdown_item
        )
        binding.spinnerBusinessType.adapter = businessAdapter

        val provinceLabels = arrayOf(
            "Select province (optional)",
            "Eastern Cape",
            "Free State",
            "Gauteng",
            "KwaZulu-Natal",
            "Limpopo",
            "Mpumalanga",
            "Northern Cape",
            "North West",
            "Western Cape"
        )

        val provinceAdapter = ArrayAdapter(
            this,
            android.R.layout.simple_spinner_item,
            provinceLabels
        )
        provinceAdapter.setDropDownViewResource(
            android.R.layout.simple_spinner_dropdown_item
        )
        binding.spinnerProvince.adapter = provinceAdapter

        binding.btnRegister.setOnClickListener {
            attemptRegister()
        }

        binding.tvLogin.setOnClickListener {
            finish()
        }
    }

    private fun attemptRegister() {
        val businessName =
            binding.etBusinessName.text?.toString()?.trim().orEmpty()

        val fullName =
            binding.etFullname.text?.toString()?.trim().orEmpty()

        val email =
            binding.etEmail.text?.toString()?.trim()?.lowercase().orEmpty()

        val phone =
            binding.etPhone.text?.toString()?.trim().orEmpty()

        val city =
            binding.etCity.text?.toString()?.trim().orEmpty()

        val password =
            binding.etPassword.text?.toString().orEmpty()

        val confirmPassword =
            binding.etConfirmPassword.text?.toString().orEmpty()

        val businessType =
            businessTypes[binding.spinnerBusinessType.selectedItemPosition]

        val province =
            provinces[binding.spinnerProvince.selectedItemPosition]

        if (
            businessName.isEmpty() ||
            fullName.isEmpty() ||
            email.isEmpty() ||
            password.isEmpty() ||
            confirmPassword.isEmpty()
        ) {
            showError("Please complete all required fields.")
            return
        }

        if (!android.util.Patterns.EMAIL_ADDRESS.matcher(email).matches()) {
            showError("Please enter a valid email address.")
            return
        }

        if (password.length < 8) {
            showError("Password must contain at least 8 characters.")
            return
        }

        if (password != confirmPassword) {
            showError("Your passwords do not match.")
            return
        }

        binding.tvError.visibility = View.GONE
        setLoading(true)

        lifecycleScope.launch {
            try {
                val request = RegisterRequest(
                    businessName = businessName,
                    businessType = businessType,
                    fullname = fullName,
                    email = email,
                    phone = phone,
                    province = province,
                    city = city,
                    password = password,
                    confirmPassword = confirmPassword
                )

                val response = RetrofitClient.instance.register(request)

                if (response.isSuccessful && response.body()?.success == true) {
                    Toast.makeText(
                        this@RegisterActivity,
                        "Business account created! Please log in.",
                        Toast.LENGTH_LONG
                    ).show()

                    finish()
                } else {
                    val message = try {
                        val errorJson = response.errorBody()
                            ?.string()
                            ?.let { JSONObject(it) }

                        errorJson?.optString("message")
                            ?.takeIf { it.isNotBlank() }
                            ?: response.body()?.message
                            ?: "Registration failed. Please try again."
                    } catch (_: Exception) {
                        response.body()?.message
                            ?: "Registration failed. Please try again."
                    }

                    showError(message)
                }
            } catch (e: Exception) {
                showError(
                    "Network error. Check your connection and API URL."
                )
            } finally {
                setLoading(false)
            }
        }
    }

    private fun setLoading(loading: Boolean) {
        binding.btnRegister.isEnabled = !loading
        binding.progressBar.visibility =
            if (loading) View.VISIBLE else View.GONE
    }

    private fun showError(message: String) {
        binding.tvError.text = message
        binding.tvError.visibility = View.VISIBLE
    }
}
