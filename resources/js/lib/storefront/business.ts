/**
 * Single source of truth for NAP and local SEO.
 * Keep address/phone/name identical everywhere (schema, meta, visible copy).
 *
 * oscarlimerick.com is the restaurant-owned official website.
 * Do NOT list third-party ordering platforms in sameAs / url.
 */
export const BUSINESS = {
  name: 'Doner House',
  alternateName: [
    'Doner House Limerick',
    'DonerHouse',
    "Doner House John's Street",
  ],
  legalName: 'Doner House',
  /** Emphasise owned site so Google prefers this over third-party order pages */
  description:
    "Doner House official website — order doner, kebab, pizza and more direct from our restaurant at 17 John's Street, Limerick. Collection and local delivery. Order on oscarlimerick.com or our official Doner House app.",
  shortDescription:
    "Official website of Doner House, John's Street, Limerick. Order doner & kebab online for collection or local delivery — direct from the restaurant.",
  tagline: 'Official website · Order direct in Limerick',
  url: 'https://www.oscarlimerick.com',
  email: 'info@oscarlimerick.com',
  telephone: '+35361597178',
  telephoneDisplay: '061 597 178',
  priceRange: '€',
  currenciesAccepted: 'EUR',
  paymentAccepted: ['Cash', 'Credit Card', 'Debit Card'],
  address: {
    streetAddress: "17 John's Street",
    addressLocality: 'Limerick',
    addressRegion: 'County Limerick',
    postalCode: 'V94 KR22',
    addressCountry: 'IE',
    neighborhood: 'Irishtown',
    full: "17 John's Street, Irishtown, Limerick, V94 KR22, Ireland",
    lines: ["17 John's Street", 'Irishtown, Limerick', 'V94 KR22, Ireland'] as const,
  },
  geo: {
    latitude: 52.668,
    longitude: -8.6264,
  },
  maps: {
    query: "Doner House, 17 John's Street, Limerick, Ireland",
    embedUrl:
      "https://maps.google.com/maps?q=Doner+House%2C+17+John%27s+Street%2C+Limerick%2C+Ireland&z=16&output=embed",
    directionsUrl:
      "https://www.google.com/maps/search/?api=1&query=Doner+House%2C+17+John%27s+Street%2C+Limerick%2C+Ireland",
  },
  openingHours: [
    { days: ['Monday', 'Tuesday', 'Wednesday', 'Thursday'] as const, opens: '15:00', closes: '00:30' },
    { days: ['Friday', 'Saturday', 'Sunday'] as const, opens: '15:00', closes: '01:30' },
  ],
  openingHoursText: ['Mo-Th 15:00-00:30', 'Fr-Su 15:00-01:30'] as const,
  areaServed: [
    'Limerick',
    'Irishtown',
    'City Centre Limerick',
    "John's Street Limerick",
    'County Limerick',
  ],
  servesCuisine: ['Doner', 'Kebab', 'Pizza', 'Middle Eastern', 'Mediterranean', 'Fast Food', 'Takeaway'],
  /** Owned properties only — never third-party marketplace / white-label order sites */
  sameAs: [
    'https://apps.apple.com/ie/app/doner-house/id6829818182',
  ],
  apps: {
    ios: {
      name: 'Doner House',
      url: 'https://apps.apple.com/ie/app/doner-house/id6829818182',
      appId: '6829818182',
    },
    android: {
      name: 'Doner House',
      url: 'https://play.google.com/store/apps/details?id=com.donerhouse.app',
      appId: 'com.donerhouse.app',
    },
  },
  ogImage: '/storefront/logo.png',
  logo: '/storefront/logo.png',
} as const

export const SITE_NAME = 'Doner House | Official Website Limerick'

export function absoluteUrl(path = "/") {
  const base = BUSINESS.url.replace(/\/$/, "")
  if (!path || path === "/") return `${base}/`
  return `${base}${path.startsWith("/") ? path : `/${path}`}`
}
