package com.example.logicostapp3.ui.login

import android.content.Intent
import android.os.Bundle
import android.view.View
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.credentials.CredentialManager
import androidx.credentials.CustomCredential
import androidx.credentials.GetCredentialRequest
import androidx.credentials.exceptions.GetCredentialCancellationException
import androidx.credentials.exceptions.GetCredentialException
import androidx.lifecycle.lifecycleScope
import com.example.logicostapp3.MainActivity
import com.example.logicostapp3.R
import com.example.logicostapp3.api.FirebaseService
import com.example.logicostapp3.api.SessionManager
import com.example.logicostapp3.databinding.ActivityLoginBinding
import com.example.logicostapp3.model.AuthResponse
import com.example.logicostapp3.model.LoginRequest
import com.google.android.libraries.identity.googleid.GetSignInWithGoogleOption
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential
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
        if (session.isLoggedIn() && FirebaseService.instance.isSignedIn()) {
            goToDashboard()
            return
        }

        // Clear any stale session from before Firebase sign-in.
        session.logout()

        // Normal login.
        binding.btnLogin.setOnClickListener {
            attemptLogin()
        }

        // Single sign-on with Google.
        binding.btnGoogleSignIn.setOnClickListener {
            attemptGoogleSignIn()
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
                val response = FirebaseService.instance.login(
                    LoginRequest(
                        email = email,
                        password = password
                    )
                )

                handleAuthResponse(response.body())

            } catch (e: Exception) {
                showError(
                    "Unable to connect to the server. Check your connection and try again."
                )
            } finally {
                setLoading(false)
            }
        }
    }

    private fun attemptGoogleSignIn() {
        binding.tvError.visibility = View.GONE
        setLoading(true)

        val googleOption = GetSignInWithGoogleOption.Builder(
            getString(R.string.default_web_client_id)
        ).build()

        val request = GetCredentialRequest.Builder()
            .addCredentialOption(googleOption)
            .build()

        lifecycleScope.launch {
            try {
                val result = CredentialManager.create(this@LoginActivity)
                    .getCredential(this@LoginActivity, request)

                val credential = result.credential

                if (credential is CustomCredential &&
                    credential.type == GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_CREDENTIAL
                ) {
                    val idToken = GoogleIdTokenCredential
                        .createFrom(credential.data)
                        .idToken

                    handleAuthResponse(
                        FirebaseService.instance.signInWithGoogle(idToken).body()
                    )
                } else {
                    showError("Google did not return a sign-in token.")
                }

            } catch (e: GetCredentialCancellationException) {
                // The user closed the Google account picker.
            } catch (e: GetCredentialException) {
                showError("Google sign-in is not available on this device.")
            } catch (e: Exception) {
                showError(
                    "Google sign-in failed. Check your connection and try again."
                )
            } finally {
                setLoading(false)
            }
        }
    }

    private fun handleAuthResponse(body: AuthResponse?) {
        if (body?.success != true) {
            showError(body?.message ?: "Login failed. Please try again.")
            return
        }

        val user = body.user
        val token = body.token

        if (user == null || token.isNullOrBlank()) {
            showError("The server returned an incomplete login response.")
            return
        }

        session.saveSession(
            token = token,
            id = user.id,
            name = user.fullname,
            email = user.email,
            role = user.role
        )

        goToDashboard()
    }

    private fun goToDashboard() {
        startActivity(
            Intent(this, MainActivity::class.java)
        )
        finish()
    }

    private fun setLoading(loading: Boolean) {
        binding.btnLogin.isEnabled = !loading
        binding.btnGoogleSignIn.isEnabled = !loading
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