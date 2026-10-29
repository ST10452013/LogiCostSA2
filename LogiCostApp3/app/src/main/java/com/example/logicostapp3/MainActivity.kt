package com.example.logicostapp3

import android.content.Intent
import android.os.Bundle
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.navigation.fragment.NavHostFragment
import androidx.navigation.ui.setupWithNavController
import com.example.logicostapp3.api.FirebaseService
import com.example.logicostapp3.api.SessionManager
import com.example.logicostapp3.databinding.ActivityMainBinding
import com.example.logicostapp3.ui.login.LoginActivity

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding
    private lateinit var session: SessionManager

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        session = SessionManager(this)

        // Prevent the main screen from opening without a valid session.
        if (!session.isLoggedIn() || !FirebaseService.instance.isSignedIn()) {
            openLogin()
            return
        }

        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        // Find the navigation host.
        val navHostFragment =
            supportFragmentManager.findFragmentById(R.id.nav_host_fragment)
                    as? NavHostFragment

        if (navHostFragment == null) {
            Toast.makeText(
                this,
                "Navigation could not be loaded. Please restart the app.",
                Toast.LENGTH_LONG
            ).show()

            finish()
            return
        }

        // Connect bottom navigation to the navigation graph.
        val navController = navHostFragment.navController
        binding.bottomNav.setupWithNavController(navController)
    }

    /**
     * Called by DashboardFragment when the user logs out
     * or the session has expired.
     */
    fun logout() {
        FirebaseService.instance.signOut()
        session.logout()
        openLogin()
    }

    private fun openLogin() {
        val intent = Intent(this, LoginActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or
                    Intent.FLAG_ACTIVITY_CLEAR_TASK
        }

        startActivity(intent)
        finish()
    }
}
