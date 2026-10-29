package com.example.logicostapp3.ui.dashboard


import androidx.fragment.app.Fragment
import androidx.lifecycle.lifecycleScope
import com.example.logicostapp3.MainActivity
import com.example.logicostapp3.api.FirebaseService
import com.example.logicostapp3.api.SessionManager
import com.example.logicostapp3.databinding.FragmentDashboardBinding
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Toast


class DashboardFragment : Fragment() {

    private var _binding: FragmentDashboardBinding? = null
    private val binding get() = _binding!!

    private lateinit var session: SessionManager

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentDashboardBinding.inflate(
            inflater,
            container,
            false
        )
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        session = SessionManager(requireContext())

        binding.tvWelcome.text =
            "Welcome, ${session.getUserName().ifBlank { "User" }}!"

        binding.tvRole.text = "Role: ${
            session.getUserRole().replaceFirstChar {
                it.uppercaseChar()
            }
        }"

        binding.btnLogout.setOnClickListener {
            (activity as? MainActivity)?.logout()
        }

        binding.swipeRefresh.setOnRefreshListener {
            loadStats()
        }

        loadStats()
    }

    private fun loadStats() {
        if (_binding == null) return

        val token = session.getToken()

        if (token.isBlank()) {
            binding.swipeRefresh.isRefreshing = false

            Toast.makeText(
                requireContext(),
                "Your session has expired. Please log in again.",
                Toast.LENGTH_LONG
            ).show()

            (activity as? MainActivity)?.logout()
            return
        }

        binding.swipeRefresh.isRefreshing = true

        viewLifecycleOwner.lifecycleScope.launch {
            try {
                val response = FirebaseService.instance
                    .getDashboardStats(token)

                if (!isAdded || _binding == null) return@launch

                val body = response.body()

                if (response.isSuccessful &&
                    body?.success == true &&
                    body.data != null
                ) {
                    val stats = body.data

                    binding.tvInventoryCount.text =
                        stats.inventoryCount.toString()

                    binding.tvDeliveryCount.text =
                        stats.deliveryCount.toString()

                    binding.tvPendingCount.text =
                        stats.pendingCount.toString()

                    binding.tvLowStockCount.text =
                        stats.lowStock.toString()

                    binding.tvInTransitCount.text =
                        stats.inTransitCount.toString()

                    binding.tvDeliveredCount.text =
                        stats.deliveredCount.toString()

                } else if (response.code() == 401) {
                    Toast.makeText(
                        requireContext(),
                        "Session expired. Please log in again.",
                        Toast.LENGTH_LONG
                    ).show()

                    (activity as? MainActivity)?.logout()

                } else {
                    Toast.makeText(
                        requireContext(),
                        body?.message
                            ?: "Could not load dashboard statistics (HTTP ${response.code()}).",
                        Toast.LENGTH_LONG
                    ).show()
                }

            } catch (e: CancellationException) {
                throw e

            } catch (e: Exception) {
                if (isAdded && _binding != null) {
                    Toast.makeText(
                        requireContext(),
                        "Could not connect to the server. Check XAMPP, your API URL and network connection.",
                        Toast.LENGTH_LONG
                    ).show()
                }

            } finally {
                _binding?.swipeRefresh?.isRefreshing = false
            }
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
