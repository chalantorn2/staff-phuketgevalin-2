// src/config/company.js - Complete Configuration
// 🔄 เปลี่ยน COMPANY ตรงนี้: 'tp-travel' หรือ 'phuket-gevalin'
const CURRENT_COMPANY = "phuket-gevalin";

export const COMPANY_CONFIGS = {
  "tp-travel": {
    name: "Phuket Gevalin",
    shortName: "TP",

    // สีต่างๆ สำหรับ UI
    colors: {
      primary: "bg-blue-600",
      primaryHover: "hover:bg-blue-700",
      primaryFocus: "focus:ring-blue-500",
      secondary: "bg-blue-100",
      text: "text-blue-600",
      textLight: "text-blue-500",
      textDark: "text-blue-700",
      border: "border-blue-600",
      borderLight: "border-blue-200",
      gradient: "bg-gradient-to-r from-blue-600 to-blue-700",

      // สำหรับสถานะต่างๆ
      success: "bg-green-600",
      warning: "bg-yellow-500",
      danger: "bg-red-600",
      info: "bg-blue-500",
    },

    // Holiday Taxis API Configuration
    api: {
      key: "htscon_fd8a9d60c363c15e3be1ff427dac2e31f5ee1521eeac523fb7c655899acf414cb45135d7dcd81841",
      endpoint: "https://suppliers.holidaytaxis.com",
      version: "2025-01",
    },

    "api-test": {
      key: "htscon_40b5cf18a8c98dc9f01a7fb65806ed8aefdabfe7f6130dcd6bfbc91ab1fd4c4f79966d6ba1304650",
      endpoint: "https://suppliers.htxstaging.com",
      version: "2025-01",
    },

    // Assets
    logo: "/logo.png",
    favicon: "/logo.png",

    // Company Information
    info: {
      fullName: "Phuket Gevalin Company Limited",
      address: "",
      phone: "+66937376128",
      email: "",
      website: "",
    },
  },

  "phuket-gevalin": {
    name: "Phuket Gevalin",
    shortName: "PG",

    // สีต่างๆ สำหรับ UI
    colors: {
      primary: "bg-cyan-600",
      primaryHover: "hover:bg-cyan-700",
      primaryFocus: "focus:ring-cyan-500",
      secondary: "bg-cyan-100",
      text: "text-cyan-600",
      textLight: "text-cyan-500",
      textDark: "text-cyan-700",
      border: "border-cyan-600",
      borderLight: "border-cyan-200",
      gradient: "bg-gradient-to-r from-cyan-600 to-cyan-700",

      // สำหรับสถานะต่างๆ
      success: "bg-green-600",
      warning: "bg-yellow-500",
      danger: "bg-red-600",
      info: "bg-cyan-500",
    },

    // Holiday Taxis API Configuration
    api: {
      key: "htscon_b6fede289eec88ec1a481ebdb689347e8d9353bafc037e74de7ffd2f1cf86a0f2ee5c0fd8d337304",
      endpoint: "https://suppliers.holidaytaxis.com",
      version: "2025-01",
    },

    "api-test": {
      key: "htscon_ad65644d53f6fcad58ef405997193f3afe3ff89aed2f5895a30bf8007f1630917cce092a4128bf5f",
      endpoint: "https://suppliers.htxstaging.com",
      version: "2025-01",
    },
    // Assets
    logo: "/logo.png",
    favicon: "/logo.png",

    // Company Information
    info: {
      fullName: "Phuket Gevalin Transport Services",
      address: "",
      phone: "",
      email: "",
      website: "",
    },
  },
};

// Export current company config
export const COMPANY = COMPANY_CONFIGS[CURRENT_COMPANY];

// Helper functions
export const getCompanyClass = (type) => {
  return COMPANY.colors[type] || "";
};

export const getCompanyColor = (type) => {
  const colorMap = {
    primary: CURRENT_COMPANY === "tp-travel" ? "#2563eb" : "#0891b2",
    secondary: CURRENT_COMPANY === "tp-travel" ? "#dbeafe" : "#cffafe",
    text: CURRENT_COMPANY === "tp-travel" ? "#2563eb" : "#0891b2",
  };
  return colorMap[type] || colorMap.primary;
};

// Quick access exports
export const COMPANY_NAME = COMPANY.name;
export const COMPANY_SHORT_NAME = COMPANY.shortName;
export const API_CONFIG = COMPANY.api;
export const COMPANY_INFO = COMPANY.info;
export const COMPANY_LOGO = COMPANY.logo;

// Theme configuration for dynamic styling
export const THEME_CONFIG = {
  fontFamily: "Prompt",
  borderRadius: {
    sm: "0.375rem",
    md: "0.5rem",
    lg: "0.75rem",
    xl: "1rem",
  },
  shadows: {
    sm: "0 1px 2px 0 rgb(0 0 0 / 0.05)",
    md: "0 4px 6px -1px rgb(0 0 0 / 0.1)",
    lg: "0 10px 15px -3px rgb(0 0 0 / 0.1)",
  },
};

// Helper for generating consistent button classes
export const getButtonClass = (variant = "primary", size = "md") => {
  const baseClass =
    "font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2";

  const variants = {
    primary: `${getCompanyClass("primary")} ${getCompanyClass(
      "primaryHover"
    )} ${getCompanyClass("primaryFocus")} text-white`,
    secondary: `${getCompanyClass("secondary")} ${getCompanyClass(
      "text"
    )} hover:opacity-80 ${getCompanyClass("primaryFocus")}`,
    outline: `border ${getCompanyClass("border")} ${getCompanyClass(
      "text"
    )} hover:${getCompanyClass("primary")} hover:text-white ${getCompanyClass(
      "primaryFocus"
    )}`,
    ghost: `${getCompanyClass("text")} hover:${getCompanyClass(
      "secondary"
    )} ${getCompanyClass("primaryFocus")}`,
    danger: `bg-red-600 hover:bg-red-700 focus:ring-red-500 text-white`,
  };

  const sizes = {
    sm: "px-3 py-1.5 text-sm",
    md: "px-4 py-2 text-sm",
    lg: "px-6 py-3 text-base",
  };

  return `${baseClass} ${variants[variant] || variants.primary} ${
    sizes[size] || sizes.md
  }`;
};

// Status color helper
export const getStatusColor = (status) => {
  const statusColors = {
    // Job statuses
    new: "bg-blue-100 text-blue-800",
    assigned: "bg-yellow-100 text-yellow-800",
    in_progress: "bg-purple-100 text-purple-800",
    completed: "bg-green-100 text-green-800",
    cancelled: "bg-red-100 text-red-800",

    // Holiday Taxis statuses
    PCON: "bg-blue-100 text-blue-800",
    ACON: "bg-green-100 text-green-800",
    PCAN: "bg-orange-100 text-orange-800",
    ACAN: "bg-red-100 text-red-800",
    PAMM: "bg-yellow-100 text-yellow-800",
    AAMM: "bg-green-100 text-green-800",

    // Driver statuses
    available: "bg-green-100 text-green-800",
    busy: "bg-yellow-100 text-yellow-800",
    offline: "bg-gray-100 text-gray-800",

    // Default
    unknown: "bg-gray-100 text-gray-800",
  };

  return statusColors[status] || statusColors.unknown;
};
