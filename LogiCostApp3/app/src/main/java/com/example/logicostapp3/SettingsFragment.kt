package com.example.logicostapp3.ui.settings

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast
import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import com.example.logicostapp3.MainActivity
import com.example.logicostapp3.api.AppSettings
import com.example.logicostapp3.api.FirebaseService
import com.example.logicostapp3.api.SessionManager
import com.example.logicostapp3.databinding.FragmentSettingsBinding
import com.example.logicostapp3.util.Validation
import kotlinx.coroutines.launch

/**
 * Lets the user change their profile, business details
 * and app preferences.
 */
class SettingsFragment : Fragment() {

    private var _binding: FragmentSettingsBinding? = null
    private val binding get() = _binding!!

    private lateinit var settings: AppSettings
    private var profile: FirebaseService.Profile? = null

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentSettingsBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        settings = AppSettings(requireContext())

        showPreferences()
        loadProfile()

        // Google accounts have no LogiCost password to reset.
        binding.btnResetPassword.visibility =
            if (FirebaseService.instance.usesPassword()) View.VISIBLE else View.GONE

        binding.btnSaveProfile.setOnClickListener { saveProfile() }
        binding.btnSavePreferences.setOnClickListener { savePreferences() }
        binding.btnResetPassword.setOnClickListener { resetPassword() }
        binding.btnLogout.setOnClickListener {
            (activity as? MainActivity)?.logout()
        }
    }

    private fun showPreferences() {
        binding.etFuelConsumption.setText(settings.fuelConsumption.toString())
        binding.etFuelPrice.setText(settings.fuelPrice.toString())
        binding.etLowStock.setText(settings.lowStockThreshold.toString())
        binding.switchConfirmDelete.isChecked = settings.confirmBeforeDelete
    }

    private fun loadProfile() {
        setLoading(true)

        viewLifecycleOwner.lifecycleScope.launch {
            try {
                val loaded = FirebaseService.instance.getProfile()
                if (_binding == null) return@launch

                profile = loaded
                binding.tvEmail.text = "Signed in as ${loaded.email}"
                binding.etFullName.setText(loaded.fullname)
                binding.etBusinessName.setText(loaded.businessName)
                binding.etPhone.setText(loaded.phone)
                binding.etProvince.setText(loaded.province)
                binding.etCity.setText(loaded.city)
            } catch (e: Exception) {
                toast("Could not load your profile. Check your connection.")
            } finally {
                if (_binding != null) setLoading(false)
            }
        }
    }

    private fun saveProfile() {
        val current = profile ?: run {
            toast("Your profile is still loading.")
            return
        }

        val fullname = binding.etFullName.text.toString().trim()
        val businessName = binding.etBusinessName.text.toString().trim()
        val phone = binding.etPhone.text.toString().trim()
        val province = binding.etProvince.text.toString().trim()
        val city = binding.etCity.text.toString().trim()

        binding.tilFullName.error =
            if (Validation.isValidName(fullname)) null else "Enter your full name"
        binding.tilBusinessName.error =
            if (Validation.isValidName(businessName)) null else "Enter your business name"
        binding.tilPhone.error =
            if (phone.isEmpty() || Validation.isValidPhone(phone)) null
            else "Use a South African number, e.g. 0821234567"

        if (binding.tilFullName.error != null ||
            binding.tilBusinessName.error != null ||
            binding.tilPhone.error != null
        ) return

        val updated = current.copy(
            fullname = fullname,
            businessName = businessName,
            phone = phone,
            province = province,
            city = city
        )

        setLoading(true)

        viewLifecycleOwner.lifecycleScope.launch {
            try {
                FirebaseService.instance.updateProfile(updated)
                profile = updated

                // Keep the dashboard greeting in step with the new name.
                val session = SessionManager(requireContext())
                session.saveSession(
                    token = session.getToken().removePrefix("Bearer "),
                    id = session.getUserId(),
                    name = fullname,
                    email = session.getUserEmail(),
                    role = session.getUserRole()
                )

                toast("Profile saved.")
            } catch (e: Exception) {
                toast("Could not save your profile. Check your connection.")
            } finally {
                if (_binding != null) setLoading(false)
            }
        }
    }

    private fun savePreferences() {
        val consumption = Validation.parseMoney(binding.etFuelConsumption.text.toString())
        val price = Validation.parseMoney(binding.etFuelPrice.text.toString())
        val lowStock = Validation.parseQuantity(binding.etLowStock.text.toString())

        binding.tilFuelConsumption.error =
            if (consumption != null && consumption in 1.0..100.0) null
            else "Enter a value between 1 and 100"
        binding.tilFuelPrice.error =
            if (price != null && price in 1.0..100.0) null
            else "Enter a price between R1 and R100"
        binding.tilLowStock.error =
            if (lowStock != null && lowStock <= 10_000) null
            else "Enter a whole number from 0 to 10 000"

        if (binding.tilFuelConsumption.error != null ||
            binding.tilFuelPrice.error != null ||
            binding.tilLowStock.error != null
        ) return

        settings.fuelConsumption = consumption!!
        settings.fuelPrice = price!!
        settings.lowStockThreshold = lowStock!!
        settings.confirmBeforeDelete = binding.switchConfirmDelete.isChecked

        toast("Preferences saved.")
    }

    private fun resetPassword() {
        setLoading(true)

        viewLifecycleOwner.lifecycleScope.launch {
            try {
                FirebaseService.instance.sendPasswordReset()
                toast("Password reset email sent. Check your inbox.")
            } catch (e: Exception) {
                toast("Could not send the reset email. Try again later.")
            } finally {
                if (_binding != null) setLoading(false)
            }
        }
    }

    private fun setLoading(loading: Boolean) {
        binding.progressBar.visibility = if (loading) View.VISIBLE else View.GONE
        binding.btnSaveProfile.isEnabled = !loading
        binding.btnResetPassword.isEnabled = !loading
    }

    private fun toast(message: String) {
        context?.let { Toast.makeText(it, message, Toast.LENGTH_LONG).show() }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
