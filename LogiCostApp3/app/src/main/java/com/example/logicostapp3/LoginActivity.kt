package com.example.logicostapp3.ui.login

import android.content.Intent
import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.example.logicostapp3.MainActivity
import com.example.logicostapp3.api.RetrofitClient
import com.example.logicostapp3.api.SessionManager
import com.example.logicostapp3.databinding.ActivityLoginBinding
import com.example.logicostapp3.model.LoginRequest
import com.example.logicostapp3.ui.register.RegisterActivity
import kotlinx.coroutines.launch

class LoginActivity : AppCompatActivity() {

    private lateinit var binding: ActivityLoginBinding
    private lateinit var session: SessionManager

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        binding = ActivityLoginBinding.inflate(layoutInflater)
        setContentView(binding.root)

        session = SessionManager(this)

        // If already logged in, go straight to the dashboard.
        if (session.isLoggedIn()) {
            goToDashboard()
            return
        }



        // Normal login.
        binding.btnLogin.setOnClickListener {
            attemptLogin()
        }

        // Keep the development bypass unavailable.
        binding.btnSkipLogin.visibility = View.GONE

        // Open registration.
        binding.tvRegister.setOnClickListener {
            startActivity(
                Intent(this, RegisterActivity::class.java)
            )
        }

        /*

        // Fill demo credentials without bypassing authentication.
        binding.tvDemo.setOnClickListener {
            binding.etEmail.setText("demo@logiscost.co.za")
            binding.etPassword.setText("demo123")
        }

         */
    }

    private fun attemptLogin() {
        val email = binding.etEmail.text.toString()
            .trim()
            .lowercase()

        val password = binding.etPassword.text.toString()

        binding.tvError.visibility = View.GONE

        if (email.isBlank() || password.isBlank()) {
            showError("Please enter your email and password.")
            return
        }

        setLoading(true)

        lifecycleScope.launch {
            try {
                val response = RetrofitClient.instance.login(
                    LoginRequest(
                        email = email,
                        password = password
                    )
                )

                val body = response.body()

                if (response.isSuccessful && body?.success == true) {
                    val user = body.user
                    val token = body.token

                    if (user == null || token.isNullOrBlank()) {
                        showError(
                            "The server returned an incomplete login response."
                        )
                        return@launch
                    }

                    session.saveSession(
                        token = token,
                        id = user.id,
                        name = user.fullname,
                        email = user.email,
                        role = user.role
                    )

                    goToDashboard()

                } else {
                    val message = body?.message
                        ?: when (response.code()) {
                            401 -> "Invalid login details or inactive account."
                            404 -> "Login API not found. Check the server URL."
                            else -> "Login failed. Server error: ${response.code()}"
                        }

                    showError(message)
                }

            } catch (e: Exception) {
                showError(
                    "Unable to connect to the server. Check your connection and try again."
                )
            } finally {
                setLoading(false)
            }
        }
    }

    private fun goToDashboard() {
        startActivity(
            Intent(this, MainActivity::class.java)
        )
        finish()
    }

    private fun setLoading(loading: Boolean) {
        binding.btnLogin.isEnabled = !loading
        binding.btnSkipLogin.isEnabled = !loading

        binding.progressBar.visibility =
            if (loading) View.VISIBLE else View.GONE
    }

    private fun showError(message: String) {
        binding.tvError.text = message
        binding.tvError.visibility = View.VISIBLE

        Toast.makeText(
            this,
            message,
            Toast.LENGTH_LONG
        ).show()
    }
}