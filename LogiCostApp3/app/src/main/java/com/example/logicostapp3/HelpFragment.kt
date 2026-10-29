package com.example.logicostapp3.ui.help

import android.content.ActivityNotFoundException
import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.util.TypedValue
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.LinearLayout
import android.widget.TextView
import android.widget.Toast
import androidx.core.content.ContextCompat
import androidx.fragment.app.Fragment
import com.example.logicostapp3.R
import com.example.logicostapp3.databinding.FragmentHelpBinding
import com.google.android.material.card.MaterialCardView

/**
 * In-app help system: a user guide for each part of the app
 * plus answers to common problems.
 */
class HelpFragment : Fragment() {

    private var _binding: FragmentHelpBinding? = null
    private val binding get() = _binding!!

    private data class Topic(val title: String, val body: String)

    private val topics = listOf(
        Topic(
            "Getting started",
            "1. Tap Register on the login screen and enter your business and personal details.\n" +
                    "2. Or tap Sign in with Google to use your Google account.\n" +
                    "3. New businesses start with sample products and deliveries so you can explore the app.\n" +
                    "4. Use the bottom bar to move between Dashboard, Inventory, Deliveries, Routes, Calculator and Marketplace."
        ),
        Topic(
            "Dashboard",
            "The dashboard shows totals for products, deliveries, pending jobs, low-stock items, deliveries in transit and completed deliveries.\n\n" +
                    "Pull down on the screen to refresh the numbers. Settings, Help and Logout are at the bottom."
        ),
        Topic(
            "Managing inventory",
            "• Tap the + button to add a product. SKU, name, quantity, low-stock level and prices are required.\n" +
                    "• Search by product name, SKU or category.\n" +
                    "• Change the quantity, low-stock level or prices on a product card, then tap Update. Tap Delete to remove it.\n" +
                    "• Products at or below their low-stock level are flagged so you know what to re-order.\n" +
                    "• Each SKU can only be used once per business."
        ),
        Topic(
            "Creating and tracking deliveries",
            "• Tap the + button on Deliveries to create a job with pickup, drop-off, package and recipient details.\n" +
                    "• Each delivery moves through Pending, Assigned, In Transit and Delivered.\n" +
                    "• Tap Assign to add a driver, then use the buttons on the card to move the job along, mark it delivered or cancel it.\n" +
                    "• Delivered and cancelled jobs stay in the list as a record."
        ),
        Topic(
            "Routes",
            "The Routes screen shows vehicles on the road with their driver, current location, destination, estimated arrival time and status. It currently shows example routes."
        ),
        Topic(
            "Cost calculator",
            "Enter the trip distance, the vehicle's fuel use (litres per 100 km) and the fuel price. Add tolls, driver wages and other costs if you have them.\n\n" +
                    "Tap Calculate to see the fuel cost, total trip cost and cost per kilometre in rand. " +
                    "Your usual fuel use and fuel price can be saved in Settings so they are filled in for you."
        ),
        Topic(
            "Marketplace",
            "• Browse products, transport and services offered by other businesses.\n" +
                    "• Publish your own listing with a title, description, location and price.\n" +
                    "• Request a quote on another business's listing. The seller can reply with a price, and you can accept or decline it.\n" +
                    "• You cannot request a quote on your own listing."
        ),
        Topic(
            "Settings",
            "• Update your name, business name, phone number, province and city.\n" +
                    "• Save your vehicle's fuel use and the fuel price for the Cost Calculator.\n" +
                    "• Choose the default low-stock alert level for new products.\n" +
                    "• Choose whether the app asks before deleting a product.\n" +
                    "• Email accounts can request a password reset link."
        ),
        Topic(
            "Your data and security",
            "Your account is protected by Firebase Authentication and your password is never stored in plain text. " +
                    "Business data is stored in the cloud, and only people signed in to your business can see your inventory and deliveries. " +
                    "Marketplace listings are visible to all LogiCost businesses."
        ),
        Topic(
            "Troubleshooting",
            "• \"Unable to connect\": check that you have mobile data or Wi-Fi, then try again.\n" +
                    "• \"Incorrect email or password\": check the spelling, or use the password reset link in Settings.\n" +
                    "• Google sign-in does nothing: make sure a Google account is added to your phone.\n" +
                    "• Numbers look out of date: pull down on the Dashboard to refresh.\n" +
                    "• Still stuck? Email support using the button below."
        )
    )

    override fun onCreateView(
        inflater: LayoutInflater,
        container: ViewGroup?,
        savedInstanceState: Bundle?
    ): View {
        _binding = FragmentHelpBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)

        topics.forEachIndexed { index, topic ->
            binding.topicsContainer.addView(topicCard(topic, expanded = index == 0))
        }

        binding.btnContactSupport.setOnClickListener {
            val email = Intent(Intent.ACTION_SENDTO).apply {
                data = Uri.parse("mailto:")
                putExtra(Intent.EXTRA_EMAIL, arrayOf("support@logicost.co.za"))
                putExtra(Intent.EXTRA_SUBJECT, "LogiCost app support")
            }

            try {
                startActivity(email)
            } catch (e: ActivityNotFoundException) {
                Toast.makeText(
                    requireContext(),
                    "No email app found. Email support@logicost.co.za",
                    Toast.LENGTH_LONG
                ).show()
            }
        }
    }

    private fun topicCard(topic: Topic, expanded: Boolean): MaterialCardView {
        val context = requireContext()
        fun dp(value: Int) = TypedValue.applyDimension(
            TypedValue.COMPLEX_UNIT_DIP, value.toFloat(), resources.displayMetrics
        ).toInt()

        val title = TextView(context).apply {
            text = (if (expanded) "▾  " else "▸  ") + topic.title
            setTextColor(ContextCompat.getColor(context, R.color.navy))
            setTextSize(TypedValue.COMPLEX_UNIT_SP, 16f)
            setTypeface(typeface, android.graphics.Typeface.BOLD)
        }

        val body = TextView(context).apply {
            text = topic.body
            setTextColor(ContextCompat.getColor(context, R.color.grey_text))
            setTextSize(TypedValue.COMPLEX_UNIT_SP, 14f)
            setLineSpacing(0f, 1.2f)
            setPadding(0, dp(8), 0, 0)
            visibility = if (expanded) View.VISIBLE else View.GONE
        }

        val content = LinearLayout(context).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(dp(16), dp(14), dp(16), dp(14))
            addView(title)
            addView(body)
        }

        return MaterialCardView(context).apply {
            layoutParams = LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                LinearLayout.LayoutParams.WRAP_CONTENT
            ).apply { bottomMargin = dp(10) }
            radius = dp(12).toFloat()
            cardElevation = dp(2).toFloat()
            isClickable = true
            contentDescription = topic.title
            addView(content)

            setOnClickListener {
                val show = body.visibility != View.VISIBLE
                body.visibility = if (show) View.VISIBLE else View.GONE
                title.text = (if (show) "▾  " else "▸  ") + topic.title
            }
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
