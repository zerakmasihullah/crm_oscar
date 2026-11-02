<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, ref } from 'vue';
import Header from '@/components/storefront/Header.vue';
import JsonLd from '@/components/storefront/JsonLd.vue';
import MyAccountModal from '@/components/storefront/MyAccountModal.vue';
import StorefrontSeo from '@/components/storefront/StorefrontSeo.vue';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import { breadcrumbJsonLd } from '@/lib/storefront/json-ld';

defineOptions({ layout: StorefrontLayout });

function goBack() {
    window.history.back();
}

const isAccountModalOpen = ref(false);
const lastUpdated = computed(() =>
    new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' }),
);
</script>

<template>
    <StorefrontSeo title="Terms of Use" path="/terms-of-use" />

    <JsonLd
        :data="
            breadcrumbJsonLd([
                { name: 'Home', path: '/' },
                { name: 'Terms of Use', path: '/terms-of-use' },
            ])
        "
    />

    <div class="min-h-screen bg-background transition-colors duration-300">
      <Header @menu-click="isAccountModalOpen = true" />
      
      <div class="max-w-4xl mx-auto px-3 sm:px-4 py-3 sm:py-4 pb-6">
        <button
          @click="goBack"
          class="flex items-center gap-1.5 text-muted-foreground hover:text-foreground mb-3 sm:mb-4 transition text-sm touch-manipulation"
        >
          <ArrowLeft class="w-4 h-4" />
          <span>Back</span>
        </button>

        <div class="bg-card rounded-xl p-4 sm:p-6 md:p-8 border border-border shadow-sm">
          <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-foreground mb-6">
            Terms of Use
          </h1>
          
          <div class="prose prose-invert max-w-none space-y-6 text-muted-foreground">
            <p class="text-sm sm:text-base leading-relaxed">
              <strong class="text-foreground">Last Updated:</strong> {{ lastUpdated }}
            </p>
            <p class="text-sm sm:text-base leading-relaxed">
              These Terms of Use apply to the Doner House website, mobile app, and ordering services operated from Ireland. They are governed by the laws of Ireland and applicable European Union consumer law as applied in Ireland.
            </p>

            <section class="space-y-4">
              <h2 class="text-xl sm:text-2xl font-bold text-foreground mt-8 mb-4">1. Who We Are</h2>
              <p class="text-sm sm:text-base leading-relaxed">
                In these terms, &quot;we&quot;, &quot;us&quot;, and &quot;our&quot; means Doner House, trading from 17 John&apos;s Street, Limerick, Ireland. You can contact us by email at 
                <a href="mailto:support@oscarlimerick.com" class="text-orange-500 hover:underline">support@oscarlimerick.com</a>
                , by phone on 
                <a href="tel:+35361597178" class="text-orange-500 hover:underline">061 597 178</a>
                , or via our 
                <Link href="/support" class="text-orange-500 hover:underline">Support page</Link>.
              </p>
            </section>

            <section class="space-y-4">
              <h2 class="text-xl sm:text-2xl font-bold text-foreground mt-8 mb-4">2. Acceptance of Terms</h2>
              <p class="text-sm sm:text-base leading-relaxed">
                By accessing or using our website, app, or ordering services, you agree to these Terms of Use and our 
                <Link href="/privacy-statement" class="text-orange-500 hover:underline">Privacy Statement</Link>.
                If you do not agree, please do not use our services. Nothing in these terms excludes or limits any rights you have as a consumer under Irish law that cannot be excluded or limited by contract.
              </p>
            </section>

            <section class="space-y-4">
              <h2 class="text-xl sm:text-2xl font-bold text-foreground mt-8 mb-4">3. Use of the Service</h2>
              <div class="space-y-3">
                <div>
                  <h3 class="text-lg font-semibold text-foreground mb-2">3.1 Eligibility</h3>
                  <p class="text-sm sm:text-base leading-relaxed">
                    You must be at least 18 years old to create an account and place an order. By using our service, you confirm that you have capacity to enter into a binding contract under Irish law.
                  </p>
                </div>
                <div>
                  <h3 class="text-lg font-semibold text-foreground mb-2">3.2 Account Registration</h3>
                  <p class="text-sm sm:text-base leading-relaxed">
                    You may need an account to place orders. You are responsible for keeping your login details secure and for activity under your account. Please tell us promptly if you believe your account has been misused.
                  </p>
                </div>
                <div>
                  <h3 class="text-lg font-semibold text-foreground mb-2">3.3 Prohibited Uses</h3>
                  <p class="text-sm sm:text-base leading-relaxed">
                    You agree not to:
                  </p>
                  <ul class="list-disc list-inside space-y-2 mt-2 ml-4">
                    <li>Use the service for any unlawful purpose or in breach of Irish or EU law</li>
                    <li>Provide false or misleading information when ordering</li>
                    <li>Transmit viruses, malware, or harmful code</li>
                    <li>Attempt to gain unauthorised access to our systems</li>
                    <li>Interfere with or disrupt the service</li>
                    <li>Use automated systems to access the service without our permission</li>
                  </ul>
                </div>
              </div>
            </section>

            <section class="space-y-4">
              <h2 class="text-xl sm:text-2xl font-bold text-foreground mt-8 mb-4">4. Orders and Payment</h2>
              <div class="space-y-3">
                <div>
                  <h3 class="text-lg font-semibold text-foreground mb-2">4.1 Order Placement</h3>
                  <p class="text-sm sm:text-base leading-relaxed">
                    When you place an order online or through the app, you are offering to buy food and related products from us. A contract is formed when we accept your order (for example, by confirming it in the app, by email/SMS, or by beginning to prepare it). We may decline an order if items are unavailable, if there is an obvious pricing error, if delivery is outside our area, or for another legitimate reason. If we decline after payment has been taken, we will refund you.
                  </p>
                </div>
                <div>
                  <h3 class="text-lg font-semibold text-foreground mb-2">4.2 Pricing and VAT</h3>
                  <p class="text-sm sm:text-base leading-relaxed">
                    All prices are shown in Euro (€) and include Irish VAT where applicable, unless we clearly state otherwise. Delivery fees and any other charges are shown before you complete checkout. We may update prices from time to time; the price that applies is the one displayed when you place your order, except where an obvious error has occurred.
                  </p>
                </div>
                <div>
                  <h3 class="text-lg font-semibold text-foreground mb-2">4.3 Payment</h3>
                  <p class="text-sm sm:text-base leading-relaxed">
                    Payment may be made by cash on delivery/collection or online through our secure payment providers. By paying online, you confirm that you are authorised to use the payment method. Online payments are processed by third-party providers; we do not store full card details on our systems.
                  </p>
                </div>
                <div>
                  <h3 class="text-lg font-semibold text-foreground mb-2">4.4 Minimum Order</h3>
                  <p class="text-sm sm:text-base leading-relaxed">
                    A minimum order amount applies for delivery orders (currently €10.00, or as shown at checkout). Collection orders may have different requirements, which will be shown before you place the order.
                  </p>
                </div>
              </div>
            </section>

            <section class="space-y-4">
              <h2 class="text-xl sm:text-2xl font-bold text-foreground mt-8 mb-4">5. Delivery and Collection</h2>
              <div class="space-y-3">
                <div>
                  <h3 class="text-lg font-semibold text-foreground mb-2">5.1 Delivery</h3>
                  <p class="text-sm sm:text-base leading-relaxed">
                    We deliver within our stated delivery area in and around Limerick. Delivery fees are shown at checkout. Estimated delivery times are guidance only and are not guarantees. We are not responsible for delays caused by events outside our reasonable control (for example, extreme weather, traffic incidents, or supplier issues), but we will try to keep you informed.
                  </p>
                </div>
                <div>
                  <h3 class="text-lg font-semibold text-foreground mb-2">5.2 Collection</h3>
                  <p class="text-sm sm:text-base leading-relaxed">
                    You may collect your order from our restaurant at 17 John&apos;s Street, Irishtown, Limerick. Please arrive at the collection time given. If an order is not collected within a reasonable time after it is ready, we may dispose of it for food-safety reasons and you may still be charged, unless the delay was our fault.
                  </p>
                </div>
                <div>
                  <h3 class="text-lg font-semibold text-foreground mb-2">5.3 Delivery Address</h3>
                  <p class="text-sm sm:text-base leading-relaxed">
                    You must provide a complete and accurate delivery address and contact number. We are not responsible for failed or delayed delivery caused by incorrect information you supplied, provided we acted with reasonable care.
                  </p>
                </div>
              </div>
            </section>

            <section class="space-y-4">
              <h2 class="text-xl sm:text-2xl font-bold text-foreground mt-8 mb-4">6. Cancellations, Cooling-Off, and Refunds</h2>
              <div class="space-y-3">
                <div>
                  <h3 class="text-lg font-semibold text-foreground mb-2">6.1 Cancelling Before Preparation</h3>
                  <p class="text-sm sm:text-base leading-relaxed">
                    You may ask us to cancel an order before preparation has begun. Contact us as soon as possible by phone or through our 
                    <Link href="/support" class="text-orange-500 hover:underline">Support page</Link>.
                    Once preparation has started, cancellation may no longer be possible because the food is made to your order and is perishable.
                  </p>
                </div>
                <div>
                  <h3 class="text-lg font-semibold text-foreground mb-2">6.2 Distance Selling / Cooling-Off Rights</h3>
                  <p class="text-sm sm:text-base leading-relaxed">
                    Under the European Union (Consumer Information, Cancellation and Other Rights) Regulations 2013, consumers generally have a 14-day cooling-off right for many distance contracts. That right does <strong class="text-foreground">not</strong> normally apply to the supply of food and drink intended for immediate consumption, or goods that are liable to deteriorate or expire rapidly, or goods made to the consumer&apos;s specifications. Our takeaway and delivery meals fall within those exceptions. This does not affect your other statutory rights if goods or services are faulty or not as described.
                  </p>
                </div>
                <div>
                  <h3 class="text-lg font-semibold text-foreground mb-2">6.3 Statutory Rights and Refunds</h3>
                  <p class="text-sm sm:text-base leading-relaxed">
                    Under the Consumer Rights Act 2022 and other applicable Irish consumer law, goods and services must be as described, of satisfactory quality, and fit for purpose. If your order is incorrect, incomplete, damaged, unsafe, or otherwise not in conformity with the contract, please contact us within a reasonable time (ideally within 24 hours of delivery or collection) with details and, where helpful, photos. We will assess the issue and, where your rights apply, offer an appropriate remedy such as a replacement, partial refund, or full refund. Any goodwill refunds beyond your legal rights are at our discretion.
                  </p>
                </div>
              </div>
            </section>

            <section class="space-y-4">
              <h2 class="text-xl sm:text-2xl font-bold text-foreground mt-8 mb-4">7. Food Allergies and Information</h2>
              <p class="text-sm sm:text-base leading-relaxed">
                If you or anyone you are ordering for has a food allergy or intolerance, you must tell us before ordering and check allergen information with the restaurant by phone on 
                <a href="tel:+35361597178" class="text-orange-500 hover:underline">061 597 178</a>.
                Do not place an order if you cannot obtain the allergen information you need. We take food safety seriously, but we cannot guarantee that any item is completely free from allergens due to the shared kitchen environment.
              </p>
            </section>

            <section class="space-y-4">
              <h2 class="text-xl sm:text-2xl font-bold text-foreground mt-8 mb-4">8. Intellectual Property</h2>
              <p class="text-sm sm:text-base leading-relaxed">
                Content on our website and app — including text, graphics, logos, images, and software — is owned by Doner House or our licensors and is protected by Irish and international intellectual property laws. You may not copy, distribute, or create derivative works from it without our written permission, except where Irish law allows (for example, limited personal use or other permitted exceptions).
              </p>
            </section>

            <section class="space-y-4">
              <h2 class="text-xl sm:text-2xl font-bold text-foreground mt-8 mb-4">9. Limitation of Liability</h2>
              <p class="text-sm sm:text-base leading-relaxed">
                Nothing in these terms excludes or limits our liability for:
              </p>
              <ul class="list-disc list-inside space-y-2 mt-2 ml-4">
                <li>Death or personal injury caused by our negligence</li>
                <li>Fraud or fraudulent misrepresentation</li>
                <li>Any other liability that cannot be excluded or limited under Irish law, including your mandatory consumer rights under the Consumer Rights Act 2022</li>
              </ul>
              <p class="text-sm sm:text-base leading-relaxed mt-3">
                Subject to the above, we are not liable for indirect or consequential loss (such as loss of profit, opportunity, or data) arising from your use of the service, to the extent Irish law allows us to limit such liability. Our total liability for any single order is limited to the amount you paid for that order, except where a higher amount is required by law.
              </p>
            </section>

            <section class="space-y-4">
              <h2 class="text-xl sm:text-2xl font-bold text-foreground mt-8 mb-4">10. Your Responsibilities</h2>
              <p class="text-sm sm:text-base leading-relaxed">
                You agree to compensate us for losses, costs, and expenses (including reasonable legal fees) that we suffer because you misuse the service, breach these terms, or provide unlawful or inaccurate information — except to the extent those losses were caused by our own breach of contract, negligence, or failure to comply with Irish law.
              </p>
            </section>

            <section class="space-y-4">
              <h2 class="text-xl sm:text-2xl font-bold text-foreground mt-8 mb-4">11. Changes to These Terms</h2>
              <p class="text-sm sm:text-base leading-relaxed">
                We may update these Terms of Use from time to time. The latest version will be posted on this page with an updated &quot;Last Updated&quot; date. Changes will not affect orders already accepted before the change. If a change is material, we will take reasonable steps to bring it to your attention. Continued use of the service after changes take effect means you accept the updated terms for future orders.
              </p>
            </section>

            <section class="space-y-4">
              <h2 class="text-xl sm:text-2xl font-bold text-foreground mt-8 mb-4">12. Complaints</h2>
              <p class="text-sm sm:text-base leading-relaxed">
                If you have a complaint, please contact us first so we can try to resolve it. You may also seek independent advice from the Competition and Consumer Protection Commission (CCPC) at 
                <a href="https://www.ccpc.ie" target="_blank" rel="noopener noreferrer" class="text-orange-500 hover:underline">www.ccpc.ie</a>
                , or from the European Consumer Centre Ireland at 
                <a href="https://www.eccireland.ie" target="_blank" rel="noopener noreferrer" class="text-orange-500 hover:underline">www.eccireland.ie</a>.
                The European Commission&apos;s Online Dispute Resolution platform is available at 
                <a href="https://ec.europa.eu/consumers/odr" target="_blank" rel="noopener noreferrer" class="text-orange-500 hover:underline">ec.europa.eu/consumers/odr</a>.
              </p>
            </section>

            <section class="space-y-4">
              <h2 class="text-xl sm:text-2xl font-bold text-foreground mt-8 mb-4">13. Governing Law and Disputes</h2>
              <p class="text-sm sm:text-base leading-relaxed">
                These Terms of Use and any contract between you and us are governed by the laws of Ireland. The Irish courts have jurisdiction over disputes, without prejudice to any mandatory rights you may have as a consumer to bring proceedings in another court under applicable EU rules. If any part of these terms is found to be unlawful or unenforceable, the remaining parts continue in full force.
              </p>
            </section>

            <section class="space-y-4">
              <h2 class="text-xl sm:text-2xl font-bold text-foreground mt-8 mb-4">14. Contact Information</h2>
              <p class="text-sm sm:text-base leading-relaxed">
                Questions about these Terms of Use can be sent to:
              </p>
              <div class="bg-muted/50 rounded-lg p-4 mt-4 border border-border">
                <p class="text-sm sm:text-base leading-relaxed">
                  <strong class="text-foreground">Doner House</strong><br />
                  17 John&apos;s Street, Irishtown, Limerick, V94 KR22, Ireland<br />
                  Phone: 
                  <a href="tel:+35361597178" class="text-orange-500 hover:underline">061 597 178</a>
                  <br />
                  Email: 
                  <a href="mailto:info@oscarlimerick.com" class="text-orange-500 hover:underline">info@oscarlimerick.com</a>
                  <br />
                  Support: 
                  <Link href="/support" class="text-orange-500 hover:underline">oscarlimerick.com/support</Link>
                </p>
              </div>
            </section>
          </div>
        </div>
      </div>

      <MyAccountModal 
        :is-open="isAccountModalOpen" 
        @close="isAccountModalOpen = false" 
      />
    </div>
</template>
