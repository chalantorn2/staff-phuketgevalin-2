// src/services/holidayTaxisApi.js
// Holiday Taxis API Service (ปรับจาก api.js ของคุณ)

import { API_CONFIG } from "../config/company";

/**
 * Send API Request to Holiday Taxis
 * @param {string} url - Full API URL
 * @param {string} method - HTTP method
 * @param {object} body - Request body (optional)
 */
export async function sendHolidayTaxisRequest(url, method, body = null) {
  const headers = {
    API_KEY: API_CONFIG.key,
    "Content-Type": "application/json",
    Accept: "application/json",
    VERSION: API_CONFIG.version,
  };

  const options = {
    method: method,
    headers: headers,
  };

  if (body) {
    options.body = JSON.stringify(body);
  }

  try {
    const response = await fetch(url, options);
    const timestamp = new Date().toISOString();
    const data = await response.json();

    // Log for debugging (สามารถปิดได้ใน production)
    console.log("Holiday Taxis API Request:", {
      url,
      method,
      headers: options.headers,
      body: options.body,
      status: response.status,
      data,
    });

    return {
      success: response.ok,
      status: response.status,
      timestamp: timestamp,
      data: data,
      error: response.ok ? null : data.message || "API Error",
    };
  } catch (error) {
    console.error("Holiday Taxis API Error:", error);
    return {
      success: false,
      status: 500,
      timestamp: new Date().toISOString(),
      data: null,
      error: error.message,
    };
  }
}

/**
 * Holiday Taxis API Methods
 */
export const holidayTaxisApi = {
  // 1. Retrieve Individual Booking
  async getBooking(bookingRef) {
    const url = `${API_CONFIG.endpoint}/bookings/${bookingRef}`;
    return await sendHolidayTaxisRequest(url, "GET");
  },

  // 2. Search Bookings by Last Action Date
  async searchBookingsByLastAction(dateFrom, dateTo, pageNumber = 1) {
    const url = `${API_CONFIG.endpoint}/bookings/search/since/${dateFrom}/until/${dateTo}/page/${pageNumber}`;
    return await sendHolidayTaxisRequest(url, "GET");
  },

  // 3. Search Bookings by Arrival Date
  async searchBookingsByArrival(dateFrom, dateTo, pageNumber = 1) {
    const url = `${API_CONFIG.endpoint}/bookings/search/arrivals/since/${dateFrom}/until/${dateTo}/page/${pageNumber}`;
    return await sendHolidayTaxisRequest(url, "GET");
  },

  // 4. Search Bookings by Departure Date
  async searchBookingsByDeparture(dateFrom, dateTo, pageNumber = 1) {
    const url = `${API_CONFIG.endpoint}/bookings/search/departures/since/${dateFrom}/until/${dateTo}/page/${pageNumber}`;
    return await sendHolidayTaxisRequest(url, "GET");
  },

  // 5. Retrieve Booking Notes
  async getBookingNotes(bookingRef) {
    const url = `${API_CONFIG.endpoint}/bookings/notes/${bookingRef}`;
    return await sendHolidayTaxisRequest(url, "GET");
  },

  // 6. Update Individual Booking
  async updateBooking(bookingRef, status) {
    const url = `${API_CONFIG.endpoint}/bookings/${bookingRef}`;
    const body = { status };
    return await sendHolidayTaxisRequest(url, "PUT", body);
  },

  // 7. Update Multiple Bookings
  async updateMultipleBookings(bookings) {
    const url = `${API_CONFIG.endpoint}/bookings`;
    const body = { bookings };
    return await sendHolidayTaxisRequest(url, "POST", body);
  },

  // 8. Reconfirm Bookings
  async reconfirmBookings(bookingRefs) {
    const url = `${API_CONFIG.endpoint}/bookings/reconfirm`;
    const body = {
      bookings: bookingRefs.map((ref) => ({ ref })),
    };
    return await sendHolidayTaxisRequest(url, "POST", body);
  },

  // 9. Set Driver and Vehicle
  async setDriverAndVehicle(
    bookingRef,
    vehicleIdentifier,
    driverData,
    vehicleData
  ) {
    const url = `${API_CONFIG.endpoint}/bookings/${bookingRef}/vehicles/${vehicleIdentifier}`;
    const body = {
      driver: driverData,
      vehicle: vehicleData,
    };
    return await sendHolidayTaxisRequest(url, "PUT", body);
  },

  // 10. Set Vehicle Location
  async setVehicleLocation(bookingRef, vehicleIdentifier, locationData) {
    const url = `${API_CONFIG.endpoint}/bookings/${bookingRef}/vehicles/${vehicleIdentifier}/location`;
    return await sendHolidayTaxisRequest(url, "POST", locationData);
  },

  // 11. Deallocate Vehicle
  async deallocateVehicle(bookingRef, vehicleIdentifier) {
    const url = `${API_CONFIG.endpoint}/bookings/${bookingRef}/vehicles/${vehicleIdentifier}`;
    return await sendHolidayTaxisRequest(url, "DELETE");
  },

  // 12. Retrieve Resorts
  async getResorts(startAt = 1) {
    const url = `${API_CONFIG.endpoint}/products/resorts/areas?startAt=${startAt}`;
    return await sendHolidayTaxisRequest(url, "GET");
  },
};

/**
 * Helper functions for date formatting
 */
export const dateUtils = {
  // Format date for API (YYYY-MM-DDTHH:mm:ss)
  formatForApi(date) {
    return date.toISOString().split(".")[0];
  },

  // Get today's date range
  getTodayRange() {
    const today = new Date();
    const start = new Date(today.setHours(0, 0, 0, 0));
    const end = new Date(today.setHours(23, 59, 59, 999));

    return {
      from: this.formatForApi(start),
      to: this.formatForApi(end),
    };
  },

  // Get date range for last N days
  getLastNDaysRange(days) {
    const end = new Date();
    const start = new Date();
    start.setDate(start.getDate() - days);

    return {
      from: this.formatForApi(start),
      to: this.formatForApi(end),
    };
  },
};
