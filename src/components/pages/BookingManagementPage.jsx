// src/components/pages/BookingManagementPage.jsx
import { useState, useEffect, useCallback, useMemo, useContext } from "react";
import { getCompanyClass } from "../../config/company";
import { BookingContext } from "../../App";
import { backendApi } from "../../services/backendApi";

function BookingManagementPage() {
  const { setSelectedBookingRef, setCurrentPage: setAppPage } =
    useContext(BookingContext);

  const [bookings, setBookings] = useState([]);
  const [loading, setLoading] = useState(false);
  const [refreshTrigger, setRefreshTrigger] = useState(0);
  const [pagination, setPagination] = useState({
    current_page: 1,
    total_pages: 1,
    total_records: 0,
    per_page: 20,
    has_next: false,
    has_prev: false,
  });

  // Filter states - Load from localStorage if available
  const getInitialFilters = () => {
    try {
      const savedFilters = localStorage.getItem("bookingManagementFilters");
      if (savedFilters) {
        return JSON.parse(savedFilters);
      }
    } catch (error) {
      console.error("Error loading saved filters:", error);
    }
    // Default filters
    return {
      status: "ACON", // Default to ACON (Confirmed)
      province: "all",
      dateFrom: "",
      dateTo: "",
      search: "",
      page: 1,
      sortBy: "pickup", // 'pickup'
      sortOrder: "asc", // 'asc' or 'desc'
    };
  };

  const [filters, setFilters] = useState(getInitialFilters());

  // Province list state
  const [provinces, setProvinces] = useState([]);

  // Auto Province state
  const [autoProvinceLoading, setAutoProvinceLoading] = useState(false);
  const [autoProvinceProgress, setAutoProvinceProgress] = useState(null);

  // Sync API state
  const [syncApiLoading, setSyncApiLoading] = useState(false);

  // Fetch Single Booking Modal state
  const [showFetchModal, setShowFetchModal] = useState(false);
  const [fetchBookingRef, setFetchBookingRef] = useState("");
  const [fetchLoading, setFetchLoading] = useState(false);
  const [fetchResult, setFetchResult] = useState(null);
  const [fetchError, setFetchError] = useState(null);

  // API call function
  const fetchBookings = useCallback(async (searchFilters) => {
    try {
      setLoading(true);

      const params = new URLSearchParams({
        page: searchFilters.page,
        limit: 20,
        status: searchFilters.status,
        province: searchFilters.province,
        sort_by: searchFilters.sortBy,
        sort_order: searchFilters.sortOrder,
      });

      if (searchFilters.dateFrom)
        params.append("date_from", searchFilters.dateFrom);
      if (searchFilters.dateTo) params.append("date_to", searchFilters.dateTo);
      if (searchFilters.search.trim())
        params.append("search", searchFilters.search.trim());

      const response = await fetch(
        `${
          import.meta.env.VITE_API_BASE_URL ||
          "https://staff.phuketgevalin.com/api"
        }/bookings/database-search.php?${params}`
      );
      const data = await response.json();

      if (data.success) {
        setBookings(data.data.bookings);
        setPagination(data.data.pagination);
      } else {
        console.error("API Error:", data.error);
        setBookings([]);
      }
    } catch (error) {
      console.error("Fetch Error:", error);
      setBookings([]);
    } finally {
      setLoading(false);
    }
  }, []);

  // Handle filter changes
  const handleFilterChange = (key, value) => {
    const newFilters = { ...filters, [key]: value, page: 1 };
    setFilters(newFilters);
  };

  // Handle search (real-time with debounce)
  const handleSearchChange = (searchTerm) => {
    const newFilters = { ...filters, search: searchTerm, page: 1 };
    setFilters(newFilters);
  };

  // Handle pagination
  const handlePageChange = (page) => {
    const newFilters = { ...filters, page };
    setFilters(newFilters);
  };

  // Handle sort toggle
  const handleSortToggle = () => {
    const newOrder = filters.sortOrder === "asc" ? "desc" : "asc";
    const newFilters = { ...filters, sortOrder: newOrder, page: 1 };
    setFilters(newFilters);
  };

  // Handle clear filters
  const handleClearFilters = () => {
    const defaultFilters = {
      status: "ACON",
      province: "all",
      dateFrom: "",
      dateTo: "",
      search: "",
      page: 1,
      sortBy: "pickup",
      sortOrder: "asc",
    };
    setFilters(defaultFilters);
    localStorage.removeItem("bookingManagementFilters");
  };

  // Handle Auto Province
  const handleAutoProvince = async () => {
    if (bookings.length === 0) {
      alert("No bookings to process");
      return;
    }

    if (
      !confirm(
        `Auto-detect province for ${bookings.length} bookings?\n\nThis will update bookings that don't have a province yet.`
      )
    ) {
      return;
    }

    try {
      setAutoProvinceLoading(true);
      setAutoProvinceProgress({ processed: 0, total: bookings.length });

      const bookingRefs = bookings.map((b) => b.ref);

      const response = await fetch(
        `${
          import.meta.env.VITE_API_BASE_URL ||
          "https://staff.phuketgevalin.com/api"
        }/bookings/auto-province-batch.php`,
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ booking_refs: bookingRefs }),
        }
      );

      const data = await response.json();

      if (data.success) {
        const { success, failed, skipped } = data.data;

        let message = `Auto Province Complete!\n\n`;
        message += `✅ Success: ${success}\n`;
        if (skipped > 0)
          message += `⏭️  Skipped: ${skipped} (already has province)\n`;
        if (failed > 0) message += `❌ Failed: ${failed}\n`;

        alert(message);

        // Refresh bookings
        fetchBookings(filters);
      } else {
        alert(`Error: ${data.error}`);
      }
    } catch (error) {
      console.error("Auto Province Error:", error);
      alert("Failed to auto-detect provinces");
    } finally {
      setAutoProvinceLoading(false);
      setAutoProvinceProgress(null);
    }
  };

  // Handle Sync API - Fetch data from Holiday Taxis API for selected date
  const handleSyncApi = async () => {
    if (!filters.dateFrom) {
      alert("Please select a 'From' date first");
      return;
    }

    try {
      setSyncApiLoading(true);
      setLoading(true); // Show loading in table

      // Convert dateFrom (YYYY-MM-DD) to datetime range
      const dateFrom = `${filters.dateFrom}T00:00:00`;
      const dateTo = `${filters.dateFrom}T23:59:59`;

      // Call Manual Sync Arrivals API
      const response = await backendApi.manualSyncArrivals(dateFrom, dateTo);

      if (response.success) {
        const result = response.data;

        let message = `Sync API Complete!\n\n`;
        message += `📅 วันที่: ${filters.dateFrom}\n`;
        message += `🔍 ค้นพบ: ${result.total_found || 0} bookings\n`;
        message += `✨ ใหม่: ${result.total_new || 0} bookings\n`;
        message += `🔄 อัพเดท(เดิม): ${result.total_updated || 0} bookings\n`;

        if (result.total_failed && result.total_failed > 0) {
          message += `\n⚠️  ล้มเหลว: ${result.total_failed} bookings\n`;
          message += `ℹ️  Backfill Job จะแก้ไขให้อัตโนมัติใน 15-30 นาที\n`;

          if (
            result.failed_refs &&
            result.failed_refs.length > 0 &&
            result.failed_refs.length <= 5
          ) {
            message += `\nBooking refs ที่ล้มเหลว:\n`;
            result.failed_refs.forEach((ref) => {
              message += `  • ${ref}\n`;
            });
          }
        } else {
          message += `\n✅ ทุก booking sync สำเร็จ!\n`;
        }

        alert(message);

        // Refresh bookings
        await fetchBookings(filters);
      } else {
        alert(`Sync API Failed: ${response.error}`);
        setLoading(false); // Hide loading on error
      }
    } catch (error) {
      console.error("Sync API Error:", error);
      alert("Failed to sync data from API");
      setLoading(false); // Hide loading on error
    } finally {
      setSyncApiLoading(false);
    }
  };

  // Handle Fetch Single Booking from Production API
  const handleFetchSingleBooking = async () => {
    if (!fetchBookingRef.trim()) {
      setFetchError("Please enter booking reference");
      return;
    }

    try {
      setFetchLoading(true);
      setFetchError(null);
      setFetchResult(null);

      const response = await backendApi.holidayTaxis.getBookingByRef(
        fetchBookingRef.trim()
      );

      if (response.success) {
        setFetchResult(response.data);
        // Refresh bookings list after successful fetch
        setTimeout(() => {
          fetchBookings(filters);
        }, 500);
      } else {
        setFetchError(response.error || "Failed to fetch booking");
      }
    } catch (error) {
      console.error("Fetch Single Booking Error:", error);
      setFetchError(error.message || "Failed to fetch booking");
    } finally {
      setFetchLoading(false);
    }
  };

  // Close Modal
  const closeFetchModal = () => {
    setShowFetchModal(false);
    setFetchBookingRef("");
    setFetchResult(null);
    setFetchError(null);
  };

  // Handle Print
  const handlePrint = async () => {
    if (bookings.length === 0) {
      alert("No data to print");
      return;
    }

    // Fetch ALL bookings with current filters (no pagination limit)
    setLoading(true);
    try {
      const allBookingsResponse = await backendApi.searchBookings({
        ...filters,
        page: 1,
        limit: 9999, // Get all records
      });

      if (!allBookingsResponse.success) {
        throw new Error("Failed to fetch all bookings");
      }

      const allBookings = allBookingsResponse.data.bookings || [];

      if (allBookings.length === 0) {
        alert("No data to print");
        return;
      }

      // Build HTML for print
      const printHeader = `
        <div style="text-align: center; margin-bottom: 12px;">
          <h2 style="margin: 0 0 5px 0; font-size: 16pt;">Bookings Report</h2>
          ${
            filters.dateFrom || filters.dateTo
              ? `
            <p style="margin: 3px 0; font-size: 9pt; color: #666;">
              ${
                filters.dateFrom && filters.dateTo
                  ? `${filters.dateFrom} to ${filters.dateTo}`
                  : filters.dateFrom
                  ? `From ${filters.dateFrom}`
                  : `Until ${filters.dateTo}`
              }
            </p>
          `
              : ""
          }
          <p style="margin: 3px 0 0 0; font-size: 7pt; color: #999;">
            Generated: ${new Date().toLocaleString("en-GB")} | Total Records: ${
        allBookings.length
      }
          </p>
        </div>
      `;

      const tableRows = allBookings
        .map((booking) => {
          // Use adjusted time if available, otherwise use original time
          const effectivePickupDate =
            booking.pickupDateAdjusted || booking.pickupDate;
          const pickupTime = effectivePickupDate
            ? new Date(effectivePickupDate).toLocaleTimeString("en-GB", {
                hour: "2-digit",
                minute: "2-digit",
              })
            : "-";
          const pickupDate = effectivePickupDate
            ? new Date(effectivePickupDate).toLocaleDateString("en-GB")
            : "-";

          // Get flight number
          const flightNo =
            booking.flightNoArrival || booking.flightNoDeparture || "-";

          // Get assignment status
          const assignmentStatus = booking.is_assigned
            ? booking.assignment_status || "Assigned"
            : "Unassigned";

          return `
        <tr>
          <td style="padding: 5px; border: 1px solid #ccc; font-size: 9pt;">${
            booking.ref
          }</td>
          <td style="padding: 5px; border: 1px solid #ccc; font-size: 9pt;">
            ${booking.passenger?.name || "N/A"}<br>
            <span style="font-size: 8pt; color: #666;">${booking.pax} pax</span>
          </td>
          <td style="padding: 5px; border: 1px solid #ccc; font-size: 9pt;">${
            booking.province || "Unknown"
          }</td>
          <td style="padding: 5px; border: 1px solid #ccc; font-size: 9pt;">
            ${booking.airport || "N/A"}<br>
            <span style="color: #666; font-size: 8pt;">→</span><br>
            ${booking.accommodation?.name || booking.resort || "N/A"}
          </td>
          <td style="padding: 5px; border: 1px solid #ccc; font-size: 9pt; text-align: center;">${flightNo}</td>
          <td style="padding: 5px; border: 1px solid #ccc; font-size: 9pt; white-space: nowrap;">
            <strong>${pickupTime}</strong><br>
            <span style="font-size: 8pt; color: #666;">${pickupDate}</span>
          </td>
          <td style="padding: 5px; border: 1px solid #ccc; font-size: 9pt; text-align: center;">${assignmentStatus}</td>
        </tr>
      `;
        })
        .join("");

      const printContent = `
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="UTF-8">
        <title>Bookings Report</title>
        <style>
          /* ============================================
             PRINT SETTINGS - ปรับค่าได้ที่นี่
             ============================================ */

          @page {
            size: A4 landscape;
            margin: 8mm 10mm;  /* บนล่าง 8mm, ซ้ายขวา 10mm */
          }

          body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 10px 15px;  /* บนล่าง 10px, ซ้ายขวา 15px */
          }

          table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
          }

          th {
            background-color: #e5e7eb;
            padding: 6px;
            border: 1px solid #ccc;
            font-size: 9pt;  /* ขนาดฟอนต์หัวตาราง */
            font-weight: 600;
            text-align: left;
          }

          td {
            vertical-align: top;
            word-wrap: break-word;
          }

          /* ============================================
             COLUMN WIDTHS - ปรับความกว้างคอลัมน์ได้ที่นี่
             รวมต้องได้ 100%
             ============================================ */
          col:nth-child(1) { width: 11%; }  /* Booking Ref */
          col:nth-child(2) { width: 17%; } /* Passenger */
          col:nth-child(3) { width: 10%; }  /* Province */
          col:nth-child(4) { width: 35%; } /* Route */
          col:nth-child(5) { width: 9%; } /* Flight */
          col:nth-child(6) { width: 11%; } /* Pickup Date/Time */
          col:nth-child(7) { width: 7%; } /* Assignment */
        </style>
      </head>
      <body>
        ${printHeader}
        <table>
          <colgroup>
            <col><col><col><col><col><col><col>
          </colgroup>
          <thead>
            <tr>
              <th>Booking Ref</th>
              <th>Passenger</th>
              <th>Province</th>
              <th>Route</th>
              <th>Flight</th>
              <th>Pickup Date/Time</th>
              <th>Assignment</th>
            </tr>
          </thead>
          <tbody>
            ${tableRows}
          </tbody>
        </table>
      </body>
      </html>
    `;

      // Open new window and print
      const printWindow = window.open("", "_blank");
      printWindow.document.write(printContent);
      printWindow.document.close();

      // Wait for content to load then print
      printWindow.onload = () => {
        printWindow.focus();
        setTimeout(() => {
          printWindow.print();
          printWindow.close();
        }, 250);
      };
    } catch (error) {
      console.error("Print error:", error);
      alert("Failed to generate print. Please try again.");
    } finally {
      setLoading(false);
    }
  };

  // Save filters to localStorage whenever they change
  useEffect(() => {
    try {
      localStorage.setItem("bookingManagementFilters", JSON.stringify(filters));
    } catch (error) {
      console.error("Error saving filters:", error);
    }
  }, [filters]);

  useEffect(() => {
    const timeoutId = setTimeout(
      () => {
        fetchBookings(filters);
      },
      filters.search.trim() ? 300 : 0
    );

    return () => clearTimeout(timeoutId);
  }, [filters, fetchBookings, refreshTrigger]);

  useEffect(() => {
    const handleRefresh = () => {
      fetchBookings(filters);
    };

    window.addEventListener("refreshBookings", handleRefresh);

    return () => {
      window.removeEventListener("refreshBookings", handleRefresh);
    };
  }, [filters, fetchBookings]);

  // Fetch provinces list on mount
  useEffect(() => {
    const fetchProvinces = async () => {
      try {
        const response = await fetch(
          `${
            import.meta.env.VITE_API_BASE_URL ||
            "https://staff.phuketgevalin.com/api"
          }/bookings/provinces.php`
        );
        const data = await response.json();
        if (data.success) {
          setProvinces(data.data);
        }
      } catch (error) {
        console.error("Error fetching provinces:", error);
      }
    };

    fetchProvinces();
  }, []);

  // Calculate page numbers for pagination
  const pageNumbers = useMemo(() => {
    const { current_page, total_pages } = pagination;
    const maxVisible = 5;

    if (total_pages <= maxVisible) {
      return Array.from({ length: total_pages }, (_, i) => i + 1);
    }

    let start = Math.max(1, current_page - Math.floor(maxVisible / 2));
    let end = Math.min(total_pages, start + maxVisible - 1);

    if (end - start + 1 < maxVisible) {
      start = Math.max(1, end - maxVisible + 1);
    }

    return Array.from({ length: end - start + 1 }, (_, i) => start + i);
  }, [pagination.current_page, pagination.total_pages]);

  // Helper functions
  const formatDate = (dateString) => {
    if (!dateString) return "-";
    return new Date(dateString).toLocaleDateString("en-GB");
  };

  const formatDateTime = (dateString) => {
    if (!dateString) return "-";
    const date = new Date(dateString);
    const dateStr = date.toLocaleDateString("en-GB");
    const timeStr = date.toLocaleTimeString("en-GB", {
      hour: "2-digit",
      minute: "2-digit",
    });
    return `${dateStr} ${timeStr}`;
  };

  const getReadableStatus = (status) => {
    const statusMap = {
      PCON: "Pending Confirmation",
      ACON: "Confirmed",
      PCAN: "Pending Cancellation",
      ACAN: "Cancelled",
      PAMM: "Pending Amendment",
      AAMM: "Amendment Approved",
    };
    return statusMap[status] || status;
  };

  const getStatusAbbr = (status) => {
    const abbrMap = {
      PCON: "Pending",
      ACON: "Confirmed",
      PCAN: "Cancel Req.",
      ACAN: "Cancelled",
      PAMM: "Amend Req.",
      AAMM: "Amended",
    };
    return abbrMap[status] || status;
  };

  const cleanVehicleName = (vehicle) => {
    if (!vehicle || vehicle === "-") return "-";
    return vehicle
      .replace(/^Private\s+/, "")
      .replace(/^Shared\s+/, "")
      .trim();
  };

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">
            Booking Management
          </h1>
          <p className="text-gray-600 mt-1">
            จัดการงานทั้งหมดและมอบหมายให้คนขับ
          </p>
        </div>
        <button
          onClick={() => setShowFetchModal(true)}
          className="px-4 py-2 bg-cyan-600 text-white rounded-lg hover:bg-cyan-700 transition-colors text-sm font-medium"
        >
          <i className="fas fa-search mr-2"></i>
          ค้นหา Booking
        </button>
      </div>

      {/* Filters Section */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
          {/* Search Bar */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Search
            </label>
            <input
              type="text"
              value={filters.search}
              onChange={(e) => handleSearchChange(e.target.value)}
              placeholder="Ref or Name"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors"
            />
          </div>

          {/* Status Filter */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Status
            </label>
            <select
              value={filters.status}
              onChange={(e) => handleFilterChange("status", e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors"
            >
              <option value="all">All Status</option>
              <optgroup label="Booking Status">
                <option value="PCON">Pending Confirmation</option>
                <option value="ACON">Confirmed</option>
                <option value="ACAN">Cancelled</option>
                <option value="PAMM">Pending Amendment</option>
                <option value="AAMM">Amendment Approved</option>
              </optgroup>
              <optgroup label="Assignment Status">
                <option value="assignment:pending">Not Assigned</option>
                <option value="assignment:assigned">Assigned</option>
                <option value="assignment:active">Active Tracking</option>
                <option value="assignment:completed">
                  Assignment Completed
                </option>
                <option value="assignment:cancelled">
                  Assignment Cancelled
                </option>
              </optgroup>
            </select>
          </div>

          {/* Province Filter */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Province
            </label>
            <select
              value={filters.province}
              onChange={(e) => handleFilterChange("province", e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors"
            >
              <option value="all">All Provinces</option>
              <option value="unknown">Unknown</option>
              {provinces.map((province) => (
                <option key={province} value={province}>
                  {province}
                </option>
              ))}
            </select>
          </div>

          {/* Date From */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              From
            </label>
            <input
              type="date"
              value={filters.dateFrom}
              onChange={(e) => handleFilterChange("dateFrom", e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors"
            />
          </div>

          {/* Date To */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              To
            </label>
            <input
              type="date"
              value={filters.dateTo}
              onChange={(e) => handleFilterChange("dateTo", e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors"
            />
          </div>
        </div>

        {/* Results Summary */}
        <div className="mt-4 pt-4 border-t border-gray-200">
          <div className="flex items-center justify-between text-sm text-gray-600">
            <span>
              {pagination.is_default_query
                ? `Showing ${bookings.length} of ${pagination.total_records} recent bookings`
                : `Showing ${bookings.length} of ${pagination.total_records} bookings`}
            </span>
            <div className="flex gap-2">
              {/* Auto Province Button - Show only when province filter is "unknown" */}
              {filters.province === "unknown" && bookings.length > 0 && (
                <button
                  onClick={handleAutoProvince}
                  disabled={autoProvinceLoading || loading}
                  className="px-3 py-1.5 text-sm font-medium rounded-lg text-white bg-green-600 hover:bg-green-700 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  title="Auto-detect province for all unknown bookings"
                >
                  {autoProvinceLoading ? (
                    <>
                      <i className="fas fa-spinner fa-spin mr-2"></i>
                      Processing...
                    </>
                  ) : (
                    <>
                      <i className="fas fa-map-marked-alt mr-2"></i>
                      Auto Province
                    </>
                  )}
                </button>
              )}

              <button
                onClick={handleClearFilters}
                disabled={loading}
                className="px-3 py-1.5 text-sm font-medium rounded-lg text-gray-700 bg-gray-100 hover:bg-gray-200 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                title="Reset all filters to default"
              >
                <i className="fas fa-eraser mr-2"></i>
                Clear Filters
              </button>
              <button
                onClick={handlePrint}
                disabled={bookings.length === 0}
                className="px-3 py-1.5 text-sm font-medium rounded-lg text-white bg-purple-600 hover:bg-purple-700 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <i className="fas fa-print mr-2"></i>
                Print
              </button>
              <button
                onClick={handleSyncApi}
                disabled={syncApiLoading || !filters.dateFrom}
                className="px-3 py-1.5 text-sm font-medium rounded-lg text-white bg-orange-600 hover:bg-orange-700 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                title="Sync bookings from Holiday Taxis API for selected From date"
              >
                {syncApiLoading ? (
                  <>
                    <i className="fas fa-spinner fa-spin mr-2"></i>
                    Syncing...
                  </>
                ) : (
                  <>
                    <i className="fas fa-search mr-2"></i>
                    Sync API
                  </>
                )}
              </button>
              <button
                onClick={() => fetchBookings(filters)}
                disabled={loading}
                className={`px-3 py-1.5 text-sm font-medium rounded-lg transition-colors ${getCompanyClass(
                  "primary"
                )} ${getCompanyClass(
                  "primaryHover"
                )} text-white disabled:opacity-50 disabled:cursor-not-allowed`}
              >
                {loading ? (
                  <>
                    <i className="fas fa-spinner fa-spin mr-2"></i>
                    Loading...
                  </>
                ) : (
                  <>
                    <i className="fas fa-sync-alt mr-2"></i>
                    Refresh
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Bookings Table */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-200">
        <div className="overflow-x-auto">
          {loading ? (
            <div className="text-center py-12">
              <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i className="fas fa-spinner animate-spin text-2xl text-gray-400"></i>
              </div>
              <p className="text-gray-500 font-medium">Loading bookings...</p>
            </div>
          ) : bookings.length === 0 ? (
            <div className="text-center py-12">
              <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i className="fas fa-search text-2xl text-gray-400"></i>
              </div>
              <p className="text-gray-500 font-medium">No bookings found</p>
              <p className="text-sm text-gray-400 mt-1">
                Try adjusting your search criteria
              </p>
            </div>
          ) : (
            <table className="w-full">
              <thead className="bg-gray-50 text-nowrap">
                <tr>
                  <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                    Booking Ref
                  </th>
                  <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                    Status
                  </th>
                  <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                    Passenger
                  </th>
                  <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                    Pax
                  </th>
                  <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                    <button
                      onClick={handleSortToggle}
                      className="flex items-center space-x-2 hover:text-cyan-600 transition-colors"
                    >
                      <span>Pickup Time</span>
                      <i
                        className={`fas fa-sort-${
                          filters.sortOrder === "asc" ? "up" : "down"
                        }`}
                      ></i>
                    </button>
                  </th>
                  <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                    Vehicle
                  </th>
                  <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                    Province
                  </th>
                  <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                    Assignment
                  </th>
                </tr>
              </thead>

              <tbody className="text-nowrap">
                {bookings.map((booking, index) => (
                  <tr
                    key={index}
                    className={`border-b border-gray-100 hover:bg-gray-50 transition-colors ${
                      booking.is_old_booking ? "bg-gray-100" : ""
                    }`}
                  >
                    <td className="py-3 px-4 whitespace-nowrap">
                      <a
                        href={`#booking/${booking.ref}?from=jobs`}
                        className={`${getCompanyClass(
                          "text"
                        )} hover:underline font-medium cursor-pointer`}
                        onClick={(e) => {
                          // Only prevent default for left click
                          if (e.button === 0 && !e.ctrlKey && !e.metaKey) {
                            e.preventDefault();
                            setSelectedBookingRef({
                              ref: booking.ref,
                              fromPage: "jobs",
                            });
                            setAppPage("booking-detail");
                          }
                        }}
                      >
                        {booking.ref}
                      </a>
                    </td>
                    <td className="py-3 px-4">
                      <span
                        title={getReadableStatus(booking.status)}
                        className={`px-2 py-1 text-xs font-medium rounded-full whitespace-nowrap cursor-help ${
                          booking.status === "PCON"
                            ? "bg-cyan-100 text-cyan-800"
                            : booking.status === "ACON"
                            ? "bg-green-100 text-green-800"
                            : booking.status === "PCAN"
                            ? "bg-orange-100 text-orange-800"
                            : booking.status === "ACAN"
                            ? "bg-red-100 text-red-800"
                            : booking.status === "PAMM"
                            ? "bg-yellow-100 text-yellow-800"
                            : booking.status === "AAMM"
                            ? "bg-purple-100 text-purple-800"
                            : "bg-gray-100 text-gray-800"
                        }`}
                      >
                        {getStatusAbbr(booking.status)}
                      </span>
                    </td>
                    <td className="py-3 px-4">
                      <div>
                        <p
                          className="font-normal text-gray-900"
                          title={booking.passenger?.name || ""}
                        >
                          {booking.passenger?.name
                            ? booking.passenger.name.length > 20
                              ? booking.passenger.name.substring(0, 20) + "..."
                              : booking.passenger.name
                            : "-"}
                        </p>
                      </div>
                    </td>
                    <td className="py-3 px-4 text-center">
                      <span className="text-sm font-medium  text-gray-900">
                        {booking.pax}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-sm  text-gray-600">
                      {formatDateTime(
                        booking.pickupDateAdjusted || booking.pickupDate
                      )}
                    </td>
                    <td className="py-3 px-4 whitespace-nowrap">
                      <span className="text-sm text-gray-900">
                        {cleanVehicleName(booking.vehicle)}
                      </span>
                    </td>
                    <td className="py-3 px-4 whitespace-nowrap">
                      <span className="text-sm text-gray-900">
                        {booking.province || "Unknown"}
                      </span>
                    </td>
                    <td className="py-3 px-4 whitespace-nowrap">
                      {!booking.is_assigned ? (
                        <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                          <i className="fas fa-circle mr-1.5"></i>
                          Not Assigned
                        </span>
                      ) : booking.assignment_status === "completed" ? (
                        <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-cyan-100 text-cyan-800">
                          <i className="fas fa-check-circle mr-1.5"></i>
                          Completed
                        </span>
                      ) : booking.assignment_status === "cancelled" ? (
                        <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                          <i className="fas fa-times-circle mr-1.5"></i>
                          Cancelled
                        </span>
                      ) : (
                        <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                          <i className="fas fa-user-check mr-1.5"></i>
                          Assigned
                        </span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        {/* Pagination */}
        {pagination.total_pages > 1 && (
          <div className="px-6 py-4 border-t border-gray-200">
            <div className="flex items-center justify-between">
              <div className="text-sm text-gray-700">
                Showing{" "}
                {(pagination.current_page - 1) * pagination.per_page + 1} to{" "}
                {Math.min(
                  pagination.current_page * pagination.per_page,
                  pagination.total_records
                )}{" "}
                of {pagination.total_records}
                {pagination.is_default_query ? " recent" : ""} bookings
              </div>

              <div className="flex items-center space-x-2">
                {/* Previous Button */}
                <button
                  onClick={() => handlePageChange(pagination.current_page - 1)}
                  disabled={!pagination.has_prev}
                  className="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <i className="fas fa-chevron-left mr-1"></i>
                  Previous
                </button>

                {/* Page Numbers */}
                <div className="flex items-center space-x-1">
                  {pageNumbers.map((pageNumber) => (
                    <button
                      key={pageNumber}
                      onClick={() => handlePageChange(pageNumber)}
                      className={`px-3 py-2 text-sm font-medium rounded-lg transition-colors ${
                        pagination.current_page === pageNumber
                          ? `${getCompanyClass("primary")} text-white`
                          : "text-gray-500 bg-white border border-gray-300 hover:bg-gray-50"
                      }`}
                    >
                      {pageNumber}
                    </button>
                  ))}
                </div>

                {/* Next Button */}
                <button
                  onClick={() => handlePageChange(pagination.current_page + 1)}
                  disabled={!pagination.has_next}
                  className="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Next
                  <i className="fas fa-chevron-right ml-1"></i>
                </button>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Fetch Single Booking Modal */}
      {showFetchModal && (
        <div
          style={{
            position: "fixed",
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            backgroundColor: "rgba(0, 0, 0, 0.5)",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            zIndex: 9999,
          }}
          onClick={closeFetchModal}
        >
          <div
            style={{
              backgroundColor: "white",
              borderRadius: "12px",
              padding: "24px",
              maxWidth: "600px",
              width: "90%",
              maxHeight: "80vh",
              overflow: "auto",
              boxShadow: "0 20px 25px -5px rgba(0, 0, 0, 0.1)",
            }}
            onClick={(e) => e.stopPropagation()}
          >
            {/* Modal Header */}
            <div style={{ marginBottom: "20px" }}>
              <div
                style={{
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "space-between",
                }}
              >
                <h2
                  style={{
                    fontSize: "20px",
                    fontWeight: "600",
                    color: "#111827",
                  }}
                >
                  ค้นหา Booking จาก Holiday Taxis
                </h2>
                <button
                  onClick={closeFetchModal}
                  style={{
                    color: "#9CA3AF",
                    background: "none",
                    border: "none",
                    cursor: "pointer",
                    padding: "4px",
                  }}
                  className="hover:text-gray-600"
                >
                  <i className="fas fa-times text-xl"></i>
                </button>
              </div>
              <p
                style={{ fontSize: "14px", color: "#6B7280", marginTop: "8px" }}
              >
                ค้นหาและดึงข้อมูล Booking โดยใส่หมายเลข Booking Reference
              </p>
            </div>

            {/* Input Section */}
            <div style={{ marginBottom: "16px" }}>
              <label
                style={{
                  display: "block",
                  fontSize: "14px",
                  fontWeight: "500",
                  color: "#374151",
                  marginBottom: "8px",
                }}
              >
                หมายเลขการจอง (Booking Reference)
              </label>
              <input
                type="text"
                value={fetchBookingRef}
                onChange={(e) => setFetchBookingRef(e.target.value)}
                onKeyPress={(e) =>
                  e.key === "Enter" && handleFetchSingleBooking()
                }
                placeholder="ตัวอย่าง: TCS-25581676"
                style={{
                  width: "100%",
                  padding: "10px 12px",
                  border: "1px solid #D1D5DB",
                  borderRadius: "8px",
                  fontSize: "14px",
                  outline: "none",
                }}
                onFocus={(e) => (e.target.style.borderColor = "#3B82F6")}
                onBlur={(e) => (e.target.style.borderColor = "#D1D5DB")}
              />
            </div>

            {/* Fetch Button */}
            <button
              onClick={handleFetchSingleBooking}
              disabled={fetchLoading}
              style={{
                width: "100%",
                backgroundColor: fetchLoading ? "#93C5FD" : "#2563EB",
                color: "white",
                padding: "12px 16px",
                borderRadius: "8px",
                fontWeight: "500",
                border: "none",
                cursor: fetchLoading ? "not-allowed" : "pointer",
                marginBottom: "16px",
                fontSize: "15px",
              }}
            >
              {fetchLoading ? (
                <>
                  <i
                    className="fas fa-spinner fa-spin"
                    style={{ marginRight: "8px" }}
                  ></i>
                  กำลังค้นหา...
                </>
              ) : (
                <>
                  <i
                    className="fas fa-search"
                    style={{ marginRight: "8px" }}
                  ></i>
                  ค้นหาและดึงข้อมูล
                </>
              )}
            </button>

            {/* Error Display */}
            {fetchError && (
              <div
                style={{
                  padding: "16px",
                  backgroundColor: "#FEE2E2",
                  border: "1px solid #FCA5A5",
                  color: "#991B1B",
                  borderRadius: "8px",
                  marginTop: "16px",
                }}
              >
                <p
                  style={{
                    fontWeight: "600",
                    marginBottom: "4px",
                    display: "flex",
                    alignItems: "center",
                  }}
                >
                  <i
                    className="fas fa-exclamation-circle"
                    style={{ marginRight: "8px" }}
                  ></i>
                  ไม่สามารถค้นหาได้
                </p>
                <p style={{ fontSize: "14px" }}>{fetchError}</p>
              </div>
            )}

            {/* Success Result Display */}
            {fetchResult && (
              <div
                style={{
                  padding: "16px",
                  backgroundColor: "#ECFDF5",
                  border: "1px solid #D1FAE5",
                  borderRadius: "8px",
                  marginTop: "16px",
                  maxHeight: "400px",
                  overflow: "auto",
                }}
              >
                <div
                  style={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    marginBottom: "12px",
                  }}
                >
                  <div>
                    <p
                      style={{
                        fontWeight: "600",
                        color: "#065F46",
                        display: "flex",
                        alignItems: "center",
                      }}
                    >
                      <i
                        className="fas fa-check-circle"
                        style={{ marginRight: "8px" }}
                      ></i>
                      {fetchResult.action === "created"
                        ? "พบรายการจองใหม่!"
                        : "อัปเดตรายการจองเรียบร้อย!"}
                    </p>
                    <p
                      style={{
                        fontSize: "13px",
                        color: "#047857",
                        marginTop: "4px",
                      }}
                    >
                      ระบบได้บันทึกข้อมูลลงฐานข้อมูลแล้ว
                    </p>
                  </div>
                </div>

                {/* Summary Card */}
                <div
                  style={{
                    backgroundColor: "white",
                    borderRadius: "8px",
                    padding: "14px",
                    marginBottom: "0",
                    border: "1px solid #A7F3D0",
                  }}
                >
                  <div
                    style={{
                      display: "grid",
                      gridTemplateColumns: "auto 1fr",
                      gap: "12px 16px",
                      fontSize: "14px",
                    }}
                  >
                    <span style={{ fontWeight: "500", color: "#6B7280" }}>
                      หมายเลขจอง:
                    </span>
                    <span style={{ color: "#111827", fontWeight: "600" }}>
                      {fetchResult.booking_ref}
                    </span>

                    <span style={{ fontWeight: "500", color: "#6B7280" }}>
                      สถานะ:
                    </span>
                    <span
                      style={{
                        fontWeight: "600",
                        color:
                          fetchResult.action === "created"
                            ? "#047857"
                            : "#1D4ED8",
                      }}
                    >
                      {fetchResult.action === "created"
                        ? "รายการใหม่"
                        : "อัปเดตข้อมูลแล้ว"}
                    </span>

                    <span style={{ fontWeight: "500", color: "#6B7280" }}>
                      ชื่อผู้โดยสาร:
                    </span>
                    <span style={{ color: "#111827" }}>
                      {fetchResult.passenger || "-"}
                    </span>

                    {fetchResult.province && (
                      <>
                        <span style={{ fontWeight: "500", color: "#6B7280" }}>
                          จังหวัด:
                        </span>
                        <span style={{ color: "#111827" }}>
                          {fetchResult.province}
                        </span>
                      </>
                    )}
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

export default BookingManagementPage;
