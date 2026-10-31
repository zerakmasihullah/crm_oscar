import { BUSINESS, absoluteUrl } from "@/lib/storefront/business"



function openingHoursSpecification() {
  return BUSINESS.openingHours.flatMap((block) =>
    block.days.map((day) => ({
      "@type": "OpeningHoursSpecification",
      dayOfWeek: day,
      opens: block.opens,
      closes: block.closes,
    })),
  )
}

const restaurantId = () => `${absoluteUrl("/")}#restaurant`
const websiteId = () => `${absoluteUrl("/")}#website`
const organizationId = () => `${absoluteUrl("/")}#organization`

/** Primary local SEO entity — owned official website only */
export function restaurantJsonLd() {
  return {
    "@context": "https://schema.org",
    "@type": ["Restaurant", "LocalBusiness", "FoodEstablishment"],
    "@id": restaurantId(),
    name: BUSINESS.name,
    alternateName: [...BUSINESS.alternateName],
    legalName: BUSINESS.legalName,
    description: BUSINESS.description,
    url: absoluteUrl("/"),
    image: [absoluteUrl(BUSINESS.ogImage), absoluteUrl(BUSINESS.logo)],
    logo: absoluteUrl(BUSINESS.logo),
    telephone: BUSINESS.telephone,
    email: BUSINESS.email,
    priceRange: BUSINESS.priceRange,
    currenciesAccepted: BUSINESS.currenciesAccepted,
    paymentAccepted: BUSINESS.paymentAccepted.join(", "),
    servesCuisine: [...BUSINESS.servesCuisine],
    menu: absoluteUrl("/"),
    acceptsReservations: false,
    hasMenu: absoluteUrl("/"),
    address: {
      "@type": "PostalAddress",
      streetAddress: BUSINESS.address.streetAddress,
      addressLocality: BUSINESS.address.addressLocality,
      addressRegion: BUSINESS.address.addressRegion,
      postalCode: BUSINESS.address.postalCode,
      addressCountry: BUSINESS.address.addressCountry,
    },
    geo: {
      "@type": "GeoCoordinates",
      latitude: BUSINESS.geo.latitude,
      longitude: BUSINESS.geo.longitude,
    },
    hasMap: BUSINESS.maps.directionsUrl,
    openingHoursSpecification: openingHoursSpecification(),
    openingHours: [...BUSINESS.openingHoursText],
    areaServed: BUSINESS.areaServed.map((name) => ({
      "@type": "City",
      name,
    })),
    sameAs: [...BUSINESS.sameAs],
    parentOrganization: { "@id": organizationId() },
    contactPoint: [
      {
        "@type": "ContactPoint",
        contactType: "customer service",
        telephone: BUSINESS.telephone,
        email: BUSINESS.email,
        areaServed: "IE",
        availableLanguage: ["English", "Irish"],
        url: absoluteUrl("/support"),
      },
      {
        "@type": "ContactPoint",
        contactType: "customer support",
        telephone: BUSINESS.telephone,
        email: BUSINESS.email,
        areaServed: "Limerick",
        availableLanguage: ["English"],
        url: absoluteUrl("/"),
      },
    ],
    potentialAction: [
      {
        "@type": "OrderAction",
        name: "Order online on our official website",
        target: {
          "@type": "EntryPoint",
          urlTemplate: absoluteUrl("/"),
          inLanguage: "en-IE",
          actionPlatform: [
            "http://schema.org/DesktopWebPlatform",
            "http://schema.org/MobileWebPlatform",
            "http://schema.org/IOSPlatform",
            "http://schema.org/AndroidPlatform",
          ],
        },
        deliveryMethod: [
          "http://purl.org/goodrelations/v1#DeliveryModeOwnFleet",
          "http://purl.org/goodrelations/v1#DeliveryModePickUp",
        ],
      },
    ],
  }
}

export function organizationJsonLd() {
  return {
    "@context": "https://schema.org",
    "@type": "Organization",
    "@id": organizationId(),
    name: BUSINESS.name,
    alternateName: [...BUSINESS.alternateName],
    url: absoluteUrl("/"),
    logo: absoluteUrl(BUSINESS.logo),
    image: absoluteUrl(BUSINESS.ogImage),
    description: BUSINESS.description,
    email: BUSINESS.email,
    telephone: BUSINESS.telephone,
    address: {
      "@type": "PostalAddress",
      streetAddress: BUSINESS.address.streetAddress,
      addressLocality: BUSINESS.address.addressLocality,
      addressRegion: BUSINESS.address.addressRegion,
      postalCode: BUSINESS.address.postalCode,
      addressCountry: BUSINESS.address.addressCountry,
    },
    sameAs: [...BUSINESS.sameAs],
    contactPoint: {
      "@type": "ContactPoint",
      contactType: "customer service",
      telephone: BUSINESS.telephone,
      email: BUSINESS.email,
      url: absoluteUrl("/support"),
    },
  }
}

export function websiteJsonLd() {
  return {
    "@context": "https://schema.org",
    "@type": "WebSite",
    "@id": websiteId(),
    name: `${BUSINESS.name} Official Website`,
    alternateName: ["Oscar's Pizza Limerick", "oscarlimerick.com", BUSINESS.alternateName[0]],
    url: absoluteUrl("/"),
    description: BUSINESS.shortDescription,
    inLanguage: "en-IE",
    publisher: { "@id": organizationId() },
    about: { "@id": restaurantId() },
    copyrightHolder: { "@id": organizationId() },
    potentialAction: {
      "@type": "OrderAction",
      target: absoluteUrl("/"),
      name: "Order pizza and kebab online",
    },
  }
}

export function webPageJsonLd() {
  return {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "@id": `${absoluteUrl("/")}#webpage`,
    url: absoluteUrl("/"),
    name: `${BUSINESS.name} | Official Website — Order Pizza & Kebab in Limerick`,
    description: BUSINESS.shortDescription,
    isPartOf: { "@id": websiteId() },
    about: { "@id": restaurantId() },
    primaryImageOfPage: absoluteUrl(BUSINESS.ogImage),
    inLanguage: "en-IE",
    significantLink: [absoluteUrl("/support"), BUSINESS.apps.ios.url, BUSINESS.apps.android.url],
  }
}

/** Official mobile apps — helps Google associate brand apps with this domain */
export function mobileAppsJsonLd() {
  return [
    {
      "@context": "https://schema.org",
      "@type": "MobileApplication",
      name: BUSINESS.apps.ios.name,
      operatingSystem: "iOS",
      applicationCategory: "LifestyleApplication",
      offers: { "@type": "Offer", price: "0", priceCurrency: "EUR" },
      url: BUSINESS.apps.ios.url,
      downloadUrl: BUSINESS.apps.ios.url,
      installUrl: BUSINESS.apps.ios.url,
      author: { "@id": organizationId() },
      publisher: { "@id": organizationId() },
      description: `Official ${BUSINESS.name} iOS app — order pizza and kebab for delivery or collection in Limerick.`,
    },
    {
      "@context": "https://schema.org",
      "@type": "MobileApplication",
      name: BUSINESS.apps.android.name,
      operatingSystem: "Android",
      applicationCategory: "LifestyleApplication",
      offers: { "@type": "Offer", price: "0", priceCurrency: "EUR" },
      url: BUSINESS.apps.android.url,
      downloadUrl: BUSINESS.apps.android.url,
      installUrl: BUSINESS.apps.android.url,
      author: { "@id": organizationId() },
      publisher: { "@id": organizationId() },
      description: `Official ${BUSINESS.name} Android app — order pizza and kebab for delivery or collection in Limerick.`,
    },
  ]
}

export function breadcrumbJsonLd(items: { name: string; path: string }[]) {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((item, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: item.name,
      item: absoluteUrl(item.path),
    })),
  }
}

export function allHomeJsonLd() {
  return [
    organizationJsonLd(),
    restaurantJsonLd(),
    websiteJsonLd(),
    webPageJsonLd(),
    ...mobileAppsJsonLd(),
  ]
}
