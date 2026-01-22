// src/components/pages/BookingOverviewPage.jsx - Complete Booking Data & Export Center
import {
  useState,
  useEffect,
  useContext,
  useRef,
  useMemo,
  useCallback,
} from "react";
import { getCompanyClass } from "../../config/company";
import { backendApi } from "../../services/backendApi";
import { BookingContext } from "../../App";

function BookingOverviewPage() {
  const { setSelectedBookingRef, setCurrentPage: setAppPage } =
    useContext(BookingContext);

  const columnSelectorRef = useRef(null);
  const statusFilterRef = useRef(null);
  const provinceFilterRef = useRef(null);
  const typeFilterRef = useRef(null);

  const [bookingData, setBookingData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);

  // Pagination
  const [recordsPerPage] = useState(20);

  // Column Visibility (load from localStorage)
  const [visibleColumns, setVisibleColumns] = useState(() => {
    const saved = localStorage.getItem("bookingOverviewColumns");
    return saved
      ? JSON.parse(saved)
      : {
          bookingRef: true,
          status: true,
          bookingType: true,
          passenger: true,
          pax: true,
          pickupTime: true,
          pickupLocation: false,
          dropoffLocation: false,
          flightNumber: false,
          vehicle: true,
          province: true,
          resort: false,
          driver: false,
          vehicleNumber: false,
        };
  });

  const [showColumnSelector, setShowColumnSelector] = useState(false);
  const [showStatusDropdown, setShowStatusDropdown] = useState(false);
  const [showProvinceDropdown, setShowProvinceDropdown] = useState(false);
  const [showBookingTypeDropdown, setShowBookingTypeDropdown] = useState(false);

  // Local search state to prevent focus loss
  const [searchInput, setSearchInput] = useState("");

  // Function to get initial filters from localStorage
  const getInitialFilters = () => {
    // Default filters
    const defaultFilters = {
      status: [],
      assignmentStatus: "all",
      bookingType: [],
      province: [],
      dateFrom: "",
      dateTo: "",
      search: "",
      page: 1,
    };

    try {
      const savedFilters = localStorage.getItem("bookingOverviewFilters");
      if (savedFilters) {
        const parsed = JSON.parse(savedFilters);
        // Merge with defaults to ensure all fields exist
        return {
          ...defaultFilters,
          ...parsed,
          // Ensure arrays are always arrays
          status: Array.isArray(parsed.status) ? parsed.status : [],
          bookingType: Array.isArray(parsed.bookingType) ? parsed.bookingType : [],
          province: Array.isArray(parsed.province) ? parsed.province : [],
        };
      }
    } catch (error) {
      console.error("Error loading saved filters:", error);
    }
    return defaultFilters;
  };

  // Filters with arrays for status and province
  const [filters, setFilters] = useState(getInitialFilters());

  // Fetch booking data (accept filters as parameter like BookingManagementPage)
  const fetchBookingData = useCallback(async (searchFilters) => {
    try {
      setLoading(true);

      const response = await backendApi.exportBookingData(searchFilters);

      if (response.success) {
        setBookingData(response.data);
      }
    } catch (error) {
      console.error("Error fetching booking data:", error);
    } finally {
      setLoading(false);
    }
  }, []); // No dependencies!

  // Initialize searchInput from saved filters
  useEffect(() => {
    setSearchInput(filters.search);
  }, []); // Run only once on mount

  // Debounce search input to filters.search (prevents focus loss)
  useEffect(() => {
    const timeoutId = setTimeout(() => {
      setFilters((prev) => ({ ...prev, search: searchInput, page: 1 }));
    }, 300);

    return () => clearTimeout(timeoutId);
  }, [searchInput]);

  // Save filters to localStorage whenever they change
  useEffect(() => {
    try {
      localStorage.setItem("bookingOverviewFilters", JSON.stringify(filters));
    } catch (error) {
      console.error("Error saving filters:", error);
    }
  }, [filters]);

  // Fetch data when filters change
  useEffect(() => {
    fetchBookingData(filters);
  }, [filters, fetchBookingData]);

  // Save column preferences to localStorage
  useEffect(() => {
    localStorage.setItem(
      "bookingOverviewColumns",
      JSON.stringify(visibleColumns)
    );
  }, [visibleColumns]);

  // Close dropdowns when clicking outside
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (
        columnSelectorRef.current &&
        !columnSelectorRef.current.contains(event.target)
      ) {
        setShowColumnSelector(false);
      }
      if (
        statusFilterRef.current &&
        !statusFilterRef.current.contains(event.target)
      ) {
        setShowStatusDropdown(false);
      }
      if (
        provinceFilterRef.current &&
        !provinceFilterRef.current.contains(event.target)
      ) {
        setShowProvinceDropdown(false);
      }
      if (
        typeFilterRef.current &&
        !typeFilterRef.current.contains(event.target)
      ) {
        setShowBookingTypeDropdown(false);
      }
    };

    if (
      showColumnSelector ||
      showStatusDropdown ||
      showProvinceDropdown ||
      showBookingTypeDropdown
    ) {
      document.addEventListener("mousedown", handleClickOutside);
    }

    return () => {
      document.removeEventListener("mousedown", handleClickOutside);
    };
  }, [
    showColumnSelector,
    showStatusDropdown,
    showProvinceDropdown,
    showBookingTypeDropdown,
  ]);

  // Calculate pagination
  const indexOfLastRecord = filters.page * recordsPerPage;
  const indexOfFirstRecord = indexOfLastRecord - recordsPerPage;
  const currentRecords =
    bookingData?.bookings?.slice(indexOfFirstRecord, indexOfLastRecord) || [];
  const totalPages = Math.ceil(
    (bookingData?.bookings?.length || 0) / recordsPerPage
  );

  // Pagination handlers
  const handlePageChange = (pageNumber) => {
    setFilters((prev) => ({ ...prev, page: pageNumber }));
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  // Column toggle handler
  const handleColumnToggle = (columnKey) => {
    setVisibleColumns((prev) => ({
      ...prev,
      [columnKey]: !prev[columnKey],
    }));
  };

  // Calculate page numbers for pagination (same as BookingManagementPage)
  const pageNumbers = useMemo(() => {
    const maxVisible = 5;

    if (totalPages <= maxVisible) {
      return Array.from({ length: totalPages }, (_, i) => i + 1);
    }

    let start = Math.max(1, filters.page - Math.floor(maxVisible / 2));
    let end = Math.min(totalPages, start + maxVisible - 1);

    if (end - start + 1 < maxVisible) {
      start = Math.max(1, end - maxVisible + 1);
    }

    return Array.from({ length: end - start + 1 }, (_, i) => start + i);
  }, [filters.page, totalPages]);

  // Helper functions
  const formatDateTime = (dateString) => {
    if (!dateString || dateString === "0000-00-00 00:00:00") return "-";
    const date = new Date(dateString);
    return `${date.toLocaleDateString("en-GB")} ${date.toLocaleTimeString(
      "en-GB",
      { hour: "2-digit", minute: "2-digit" }
    )}`;
  };

  const formatDate = (dateString) => {
    if (!dateString || dateString === "0000-00-00 00:00:00") return "-";
    return new Date(dateString).toLocaleDateString("en-GB");
  };

  // Helper function to get status label (for export)
  const getStatusLabel = (status) => {
    const statusMap = {
      PCON: "Pending",
      ACON: "Confirmed",
      PCAN: "Cancel Req.",
      ACAN: "Cancelled",
      PAMM: "Amend Req.",
      AAMM: "Amended",
    };
    return statusMap[status] || status;
  };

  const getStatusBadge = (status) => {
    const statusMap = {
      PCON: { label: "Pending", color: "bg-cyan-100 text-cyan-800" },
      ACON: { label: "Confirmed", color: "bg-green-100 text-green-800" },
      PCAN: { label: "Cancel Req.", color: "bg-orange-100 text-orange-800" },
      ACAN: { label: "Cancelled", color: "bg-red-100 text-red-800" },
      PAMM: { label: "Amend Req.", color: "bg-yellow-100 text-yellow-800" },
      AAMM: { label: "Amended", color: "bg-purple-100 text-purple-800" },
    };
    const s = statusMap[status] || {
      label: status,
      color: "bg-gray-100 text-gray-800",
    };
    return (
      <span className={`px-2 py-1 text-xs font-medium rounded-full ${s.color}`}>
        {s.label}
      </span>
    );
  };

  // Get booking type with icon and color (same as BookingManagementPage)
  const getBookingType = (booking) => {
    const type = booking.booking_type || "";

    // Check from booking type field first
    if (type && type !== "N/A") {
      const lowerType = type.toLowerCase();
      if (lowerType.includes("arrival")) {
        return {
          label: "Arrival",
          icon: "fa-plane-arrival",
          color: "text-green-600",
        };
      } else if (lowerType.includes("departure")) {
        return {
          label: "Departure",
          icon: "fa-plane-departure",
          color: "text-red-600",
        };
      } else if (lowerType.includes("point")) {
        return {
          label: "P2P",
          icon: "fa-route",
          color: "text-purple-600",
        };
      }
    }

    // Fallback: Detect from dates and flight numbers
    const hasArrivalDate =
      booking.arrival_date && booking.arrival_date !== "0000-00-00 00:00:00";
    const hasDepartureDate =
      booking.departure_date &&
      booking.departure_date !== "0000-00-00 00:00:00";
    const hasArrivalFlight = booking.flight_number_arrival;
    const hasDepartureFlight = booking.flight_number_departure;

    if (hasArrivalDate || hasArrivalFlight) {
      return {
        label: "Arrival",
        icon: "fa-plane-arrival",
        color: "text-green-600",
      };
    } else if (hasDepartureDate || hasDepartureFlight) {
      return {
        label: "Departure",
        icon: "fa-plane-departure",
        color: "text-red-600",
      };
    } else if (!hasArrivalDate && !hasDepartureDate) {
      // If no arrival/departure date, likely Point to Point
      return {
        label: "P2P",
        icon: "fa-route",
        color: "text-purple-600",
      };
    }

    return {
      label: "N/A",
      icon: "fa-question",
      color: "text-gray-400",
    };
  };

  const handleViewBooking = (bookingRef) => {
    setSelectedBookingRef({
      ref: bookingRef,
      fromPage: "booking",
    });
    setAppPage("booking-detail");
  };

  const handleFilterChange = (key, value) => {
    setFilters((prev) => ({ ...prev, [key]: value, page: 1 }));
  };

  // Handle checkbox toggle for multi-select filters
  const handleCheckboxToggle = (filterType, value) => {
    setFilters((prev) => {
      const currentValues = prev[filterType];
      const newValues = currentValues.includes(value)
        ? currentValues.filter((v) => v !== value)
        : [...currentValues, value];
      return { ...prev, [filterType]: newValues, page: 1 };
    });
  };

  const handleResetFilters = () => {
    setSearchInput("");
    const defaultFilters = {
      status: [],
      assignmentStatus: "all",
      bookingType: [],
      province: [],
      dateFrom: "",
      dateTo: "",
      search: "",
      page: 1,
    };
    setFilters(defaultFilters);
    localStorage.removeItem("bookingOverviewFilters");
  };

  // Export to Excel
  const handleExportExcel = () => {
    if (!bookingData?.bookings || bookingData.bookings.length === 0) {
      alert("ไม่มีข้อมูลสำหรับ Export");
      return;
    }

    setExporting(true);

    try {
      // Build filter description
      const filterInfo = [];
      if (filters.status.length > 0)
        filterInfo.push(`Status: ${filters.status.join(", ")}`);
      if (filters.assignmentStatus !== "all")
        filterInfo.push(`Assignment: ${filters.assignmentStatus}`);
      if (filters.bookingType.length > 0)
        filterInfo.push(`Type: ${filters.bookingType.join(", ")}`);
      if (filters.province.length > 0)
        filterInfo.push(`Province: ${filters.province.join(", ")}`);
      if (filters.dateFrom) filterInfo.push(`From: ${filters.dateFrom}`);
      if (filters.dateTo) filterInfo.push(`To: ${filters.dateTo}`);
      if (filters.search) filterInfo.push(`Search: ${filters.search}`);

      // Create CSV content with header (based on visible columns)
      const reportHeader = [
        ["Booking Overview Report"],
        [`Generated: ${new Date().toLocaleString("en-GB")}`],
        filterInfo.length > 0 ? [`Filters: ${filterInfo.join(" | ")}`] : [],
        [`Total Records: ${bookingData.bookings.length}`],
        [], // Empty line
      ].filter((row) => row.length > 0);

      // Build headers based on visible columns
      const headers = [];
      if (visibleColumns.bookingRef) headers.push("Booking Ref");
      if (visibleColumns.status) headers.push("Status");
      if (visibleColumns.bookingType) headers.push("Type");
      if (visibleColumns.passenger) headers.push("Passenger");
      if (visibleColumns.pax) headers.push("PAX");
      if (visibleColumns.pickupTime) headers.push("Pickup Date");
      if (visibleColumns.pickupLocation) headers.push("From");
      if (visibleColumns.dropoffLocation) headers.push("To");
      if (visibleColumns.flightNumber) headers.push("Flight No.");
      if (visibleColumns.vehicle) headers.push("Vehicle");
      if (visibleColumns.province) headers.push("Province");
      if (visibleColumns.resort) headers.push("Resort");
      if (visibleColumns.driver) headers.push("Driver");
      if (visibleColumns.vehicleNumber) headers.push("Vehicle No.");

      // Export ALL bookings, not just current page (based on visible columns)
      const rows = (bookingData?.bookings || []).map((booking) => {
        const row = [];
        if (visibleColumns.bookingRef) row.push(booking.booking_ref || "");
        if (visibleColumns.status)
          row.push(getStatusLabel(booking.ht_status) || "");
        if (visibleColumns.bookingType) row.push(getBookingType(booking).label || "");
        if (visibleColumns.passenger) row.push(booking.lead_passenger || "");
        if (visibleColumns.pax) row.push(booking.pax_total || "");
        if (visibleColumns.pickupTime)
          row.push(formatDateTime(booking.pickup_date));
        if (visibleColumns.pickupLocation)
          row.push(booking.pickup_location || "");
        if (visibleColumns.dropoffLocation)
          row.push(booking.dropoff_location || "");
        if (visibleColumns.flightNumber) row.push(booking.flight_number || "");
        if (visibleColumns.vehicle) row.push(booking.vehicle_type || "");
        if (visibleColumns.province) row.push(booking.province || "");
        if (visibleColumns.resort) row.push(booking.resort || "");
        if (visibleColumns.driver) row.push(booking.driver_name || "");
        if (visibleColumns.vehicleNumber)
          row.push(booking.vehicle_number || "");
        return row;
      });

      // Combine all parts
      const csvContent = [
        ...reportHeader.map((row) => row.join(",")),
        headers.join(","),
        ...rows.map((row) =>
          row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(",")
        ),
      ].join("\n");

      // Create download link
      const blob = new Blob(["\ufeff" + csvContent], {
        type: "text/csv;charset=utf-8;",
      });
      const link = document.createElement("a");
      const url = URL.createObjectURL(blob);
      link.setAttribute("href", url);
      link.setAttribute(
        "download",
        `booking-export-${new Date().toISOString().split("T")[0]}.csv`
      );
      link.style.visibility = "hidden";
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    } catch (error) {
      console.error("Export error:", error);
      alert("เกิดข้อผิดพลาดในการ Export");
    } finally {
      setExporting(false);
    }
  };

  // Export to PDF (Simple version - will create a printable page)
  const handleExportPDF = () => {
    if (!bookingData?.bookings || bookingData.bookings.length === 0) {
      alert("ไม่มีข้อมูลสำหรับ Export");
      return;
    }

    // Build filter description
    const filterInfo = [];
    if (filters.status.length > 0)
      filterInfo.push(`Status: ${filters.status.join(", ")}`);
    if (filters.assignmentStatus !== "all")
      filterInfo.push(`Assignment: ${filters.assignmentStatus}`);
    if (filters.bookingType.length > 0)
      filterInfo.push(`Type: ${filters.bookingType.join(", ")}`);
    if (filters.province.length > 0)
      filterInfo.push(`Province: ${filters.province.join(", ")}`);
    if (filters.dateFrom) filterInfo.push(`From: ${filters.dateFrom}`);
    if (filters.dateTo) filterInfo.push(`To: ${filters.dateTo}`);
    if (filters.search) filterInfo.push(`Search: ${filters.search}`);

    // Create print header
    const printHeader = `
      <div style="text-align: center; margin-bottom: 20px;">
        <h2 style="margin: 0 0 10px 0; font-size: 18pt; font-weight: bold;">Booking Overview Report</h2>
        <p style="margin: 5px 0; font-size: 10pt; color: #666;">
          Generated: ${new Date().toLocaleString("en-GB")}
        </p>
        ${
          filterInfo.length > 0
            ? `
          <p style="margin: 5px 0; font-size: 9pt; color: #666;">
            Filters: ${filterInfo.join(" | ")}
          </p>
        `
            : ""
        }
        <p style="margin: 5px 0; font-size: 9pt; color: #666;">
          Total Records: ${bookingData.bookings.length}
        </p>
      </div>
    `;

    // Build table headers based on visible columns
    const tableHeaders = [];
    if (visibleColumns.bookingRef) tableHeaders.push("Booking Ref");
    if (visibleColumns.status) tableHeaders.push("Status");
    if (visibleColumns.bookingType) tableHeaders.push("Type");
    if (visibleColumns.passenger) tableHeaders.push("Passenger");
    if (visibleColumns.pax) tableHeaders.push("PAX");
    if (visibleColumns.pickupTime) tableHeaders.push("Pickup Date");
    if (visibleColumns.pickupLocation) tableHeaders.push("From");
    if (visibleColumns.dropoffLocation) tableHeaders.push("To");
    if (visibleColumns.flightNumber) tableHeaders.push("Flight No.");
    if (visibleColumns.vehicle) tableHeaders.push("Vehicle");
    if (visibleColumns.province) tableHeaders.push("Province");
    if (visibleColumns.resort) tableHeaders.push("Resort");
    if (visibleColumns.driver) tableHeaders.push("Driver");
    if (visibleColumns.vehicleNumber) tableHeaders.push("Vehicle No.");

    // Create table rows based on visible columns
    const tableRows = (bookingData?.bookings || [])
      .map((booking) => {
        const cells = [];
        if (visibleColumns.bookingRef) cells.push(booking.booking_ref || "");
        if (visibleColumns.status)
          cells.push(getStatusLabel(booking.ht_status) || "");
        if (visibleColumns.bookingType) cells.push(getBookingType(booking).label || "");
        if (visibleColumns.passenger) cells.push(booking.lead_passenger || "-");
        if (visibleColumns.pax) cells.push(booking.pax_total || "");
        if (visibleColumns.pickupTime)
          cells.push(formatDateTime(booking.pickup_date));
        if (visibleColumns.pickupLocation)
          cells.push(booking.pickup_location || "-");
        if (visibleColumns.dropoffLocation)
          cells.push(booking.dropoff_location || "-");
        if (visibleColumns.flightNumber)
          cells.push(booking.flight_number || "-");
        if (visibleColumns.vehicle) {
          const vehicleType = booking.vehicle_type
            ? booking.vehicle_type
                .replace(/^Private\s+/, "")
                .replace(/^Shared\s+/, "")
                .trim()
            : "-";
          cells.push(vehicleType);
        }
        if (visibleColumns.province) cells.push(booking.province || "Unknown");
        if (visibleColumns.resort) cells.push(booking.resort || "-");
        if (visibleColumns.driver) cells.push(booking.driver_name || "-");
        if (visibleColumns.vehicleNumber)
          cells.push(booking.vehicle_number || "-");

        return `
      <tr>
        ${cells
          .map(
            (cell) =>
              `<td style="padding: 8px; border: 1px solid #ddd; font-size: 9pt;">${cell}</td>`
          )
          .join("")}
      </tr>
    `;
      })
      .join("");

    // Create print content
    const printContent = `
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="UTF-8">
        <title>Booking Overview Report</title>
        <style>
          @page {
            size: A4 landscape;
            margin: 10mm;
          }
          body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 15px;
          }
          table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
          }
          th {
            background-color: #f3f4f6;
            padding: 10px 8px;
            border: 1px solid #ddd;
            font-size: 10pt;
            font-weight: 600;
            text-align: left;
          }
          td {
            vertical-align: top;
          }
          @media print {
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
          }
        </style>
      </head>
      <body>
        ${printHeader}
        <table>
          <thead>
            <tr>
              ${tableHeaders.map((header) => `<th>${header}</th>`).join("")}
            </tr>
          </thead>
          <tbody>
            ${tableRows}
          </tbody>
        </table>
      </body>
      </html>
    `;

    // Open print window
    const printWindow = window.open("", "_blank");
    printWindow.document.write(printContent);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
      printWindow.print();
      printWindow.close();
    }, 250);
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">
            Booking Overview
          </h1>
          <p className="text-gray-600 mt-1">
            ข้อมูลการจองทั้งหมด และระบบ Export
          </p>
        </div>

        {/* Export Buttons & Column Selector */}
        <div className="flex space-x-3">
          {/* Column Selector */}
          <div className="relative" ref={columnSelectorRef}>
            <button
              onClick={() => setShowColumnSelector(!showColumnSelector)}
              className="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors text-sm font-medium"
            >
              <i className="fas fa-columns mr-2"></i>
              Columns
            </button>

            {showColumnSelector && (
              <div className="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                <div className="p-4">
                  <h3 className="text-sm font-semibold text-gray-900 mb-3 sticky top-0 bg-white pb-2 border-b">
                    Show/Hide Columns
                  </h3>
                  <div className="space-y-2 max-h-96 overflow-y-auto pr-2">
                    {[
                      { key: "bookingRef", label: "Booking Ref" },
                      { key: "status", label: "Status" },
                      { key: "bookingType", label: "Booking Type" },
                      { key: "passenger", label: "Passenger" },
                      { key: "pax", label: "Pax" },
                      { key: "pickupTime", label: "Pickup Time" },
                      { key: "pickupLocation", label: "From" },
                      { key: "dropoffLocation", label: "To" },
                      { key: "flightNumber", label: "Flight Number" },
                      { key: "vehicle", label: "Vehicle Type" },
                      { key: "province", label: "Province" },
                      { key: "resort", label: "Resort" },
                      { key: "driver", label: "Driver Name" },
                      { key: "vehicleNumber", label: "Vehicle Number" },
                    ].map((col) => (
                      <label
                        key={col.key}
                        className="flex items-center cursor-pointer hover:bg-gray-50 p-1.5 rounded"
                      >
                        <input
                          type="checkbox"
                          checked={visibleColumns[col.key]}
                          onChange={() => handleColumnToggle(col.key)}
                          className="w-4 h-4 text-cyan-600 border-gray-300 rounded focus:ring-cyan-500"
                        />
                        <span className="ml-2 text-sm text-gray-700">
                          {col.label}
                        </span>
                      </label>
                    ))}
                  </div>
                </div>
              </div>
            )}
          </div>

          <button
            onClick={handleExportPDF}
            disabled={exporting}
            className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <i className="fas fa-file-pdf mr-2"></i>
            Export PDF
          </button>
          <button
            onClick={handleExportExcel}
            disabled={exporting}
            className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <i className="fas fa-file-excel mr-2"></i>
            {exporting ? "Exporting..." : "Export Excel"}
          </button>
        </div>
      </div>

      {/* Filters with Checkbox Dropdowns */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-4">
          {/* Search Bar - uses local state */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Search
            </label>
            <input
              type="text"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Ref or Name"
              className="w-full h-[42px] px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors"
            />
          </div>

          {/* Status Filter - Dropdown with Checkboxes */}
          <div className="relative" ref={statusFilterRef}>
            <label className="block text-sm font-medium  mb-2">Status</label>
            <button
              type="button"
              onClick={() => setShowStatusDropdown(!showStatusDropdown)}
              className="w-full h-[42px] px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors text-left bg-white flex items-center justify-between"
            >
              <span className="text-sm truncate">
                {filters.status.length === 0
                  ? "All Status"
                  : `${filters.status.length} selected`}
              </span>
              <i
                className={`fas fa-chevron-down text-gray-400 transition-transform ${
                  showStatusDropdown ? "rotate-180" : ""
                }`}
              ></i>
            </button>

            {showStatusDropdown && (
              <div className="absolute left-0 right-0 mt-2 bg-white rounded-lg shadow-lg border border-gray-200 z-10 max-h-64 overflow-y-auto">
                <div className="p-2">
                  {[
                    { value: "PCON", label: "Pending" },
                    { value: "ACON", label: "Confirmed" },
                    { value: "ACAN", label: "Cancelled" },
                    { value: "PCAN", label: "Cancel Req." },
                    { value: "PAMM", label: "Amend Req." },
                    { value: "AAMM", label: "Amended" },
                  ].map((status) => (
                    <label
                      key={status.value}
                      className="flex items-center cursor-pointer hover:bg-gray-50 p-2 rounded"
                    >
                      <input
                        type="checkbox"
                        checked={filters.status.includes(status.value)}
                        onChange={() =>
                          handleCheckboxToggle("status", status.value)
                        }
                        className="w-4 h-4 text-cyan-600 border-gray-300 rounded focus:ring-cyan-500"
                      />
                      <span className="ml-2 text-sm">{status.label}</span>
                    </label>
                  ))}
                </div>
              </div>
            )}
          </div>

          {/* Assignment Status Filter */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Assignment
            </label>
            <select
              value={filters.assignmentStatus}
              onChange={(e) =>
                handleFilterChange("assignmentStatus", e.target.value)
              }
              className="w-full h-[42px] px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors"
            >
              <option value="all">All</option>
              <option value="assigned">Assigned</option>
              <option value="pending">Not Assigned</option>
            </select>
          </div>

          {/* Booking Type Filter - Dropdown with Checkboxes */}
          <div className="relative" ref={typeFilterRef}>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Type
            </label>
            <button
              type="button"
              onClick={() =>
                setShowBookingTypeDropdown(!showBookingTypeDropdown)
              }
              className="w-full h-[42px] px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors text-left bg-white flex items-center justify-between"
            >
              <span className="text-sm truncate">
                {filters.bookingType.length === 0
                  ? "All Types"
                  : `${filters.bookingType.length} selected`}
              </span>
              <i
                className={`fas fa-chevron-down text-gray-400 transition-transform ${
                  showBookingTypeDropdown ? "rotate-180" : ""
                }`}
              ></i>
            </button>

            {showBookingTypeDropdown && (
              <div className="absolute left-0 right-0 mt-2 bg-white rounded-lg shadow-lg border border-gray-200 z-10 max-h-64 overflow-y-auto">
                <div className="p-2">
                  {[
                    { value: "arrival", label: "Arrival" },
                    { value: "departure", label: "Departure" },
                    { value: "p2p", label: "Point to Point" },
                  ].map((type) => (
                    <label
                      key={type.value}
                      className="flex items-center cursor-pointer hover:bg-gray-50 p-2 rounded"
                    >
                      <input
                        type="checkbox"
                        checked={filters.bookingType.includes(type.value)}
                        onChange={() =>
                          handleCheckboxToggle("bookingType", type.value)
                        }
                        className="w-4 h-4 text-cyan-600 border-gray-300 rounded focus:ring-cyan-500"
                      />
                      <span className="ml-2 text-sm">{type.label}</span>
                    </label>
                  ))}
                </div>
              </div>
            )}
          </div>

          {/* Province Filter - Dropdown with Checkboxes */}
          <div className="relative" ref={provinceFilterRef}>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Province
            </label>
            <button
              type="button"
              onClick={() => setShowProvinceDropdown(!showProvinceDropdown)}
              className="w-full h-[42px] px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors text-left bg-white flex items-center justify-between"
            >
              <span className=" text-sm truncate">
                {filters.province.length === 0
                  ? "All Provinces"
                  : `${filters.province.length} selected`}
              </span>
              <i
                className={`fas fa-chevron-down text-gray-400 transition-transform ${
                  showProvinceDropdown ? "rotate-180" : ""
                }`}
              ></i>
            </button>

            {showProvinceDropdown && (
              <div className="absolute left-0 right-0 mt-2 bg-white rounded-lg shadow-lg border border-gray-200 z-10 max-h-64 overflow-y-auto">
                <div className="p-2">
                  <label className="flex items-center cursor-pointer hover:bg-gray-50 p-2 rounded">
                    <input
                      type="checkbox"
                      checked={filters.province.includes("unknown")}
                      onChange={() =>
                        handleCheckboxToggle("province", "unknown")
                      }
                      className="w-4 h-4 text-cyan-600 border-gray-300 rounded focus:ring-cyan-500"
                    />
                    <span className="ml-2 text-sm">Unknown</span>
                  </label>
                  {bookingData?.provinces
                    ?.filter((p) => p !== "Unknown")
                    .map((province) => (
                      <label
                        key={province}
                        className="flex items-center cursor-pointer hover:bg-gray-50 p-2 rounded"
                      >
                        <input
                          type="checkbox"
                          checked={filters.province.includes(province)}
                          onChange={() =>
                            handleCheckboxToggle("province", province)
                          }
                          className="w-4 h-4 text-cyan-600 border-gray-300 rounded focus:ring-cyan-500"
                        />
                        <span className="ml-2 text-sm">{province}</span>
                      </label>
                    ))}
                </div>
              </div>
            )}
          </div>

          {/* Date From */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Date From
            </label>
            <input
              type="date"
              value={filters.dateFrom}
              onChange={(e) => handleFilterChange("dateFrom", e.target.value)}
              className="w-full h-[42px] px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors"
            />
          </div>

          {/* Date To */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Date To
            </label>
            <input
              type="date"
              value={filters.dateTo}
              onChange={(e) => handleFilterChange("dateTo", e.target.value)}
              className="w-full h-[42px] px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors"
            />
          </div>
        </div>

        {/* Action Buttons */}
        <div className="mt-4 pt-4 border-t border-gray-200">
          <div className="flex items-center justify-between text-sm text-gray-600">
            <span>
              {bookingData?.total
                ? `Showing ${bookingData.total} bookings`
                : "No bookings"}
            </span>
            <div className="flex gap-2">
              <button
                onClick={handleResetFilters}
                disabled={loading}
                className="px-3 py-1.5 text-sm font-medium rounded-lg text-gray-700 bg-gray-100 hover:bg-gray-200 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                title="Reset all filters to default"
              >
                <i className="fas fa-eraser mr-2"></i>
                Clear Filters
              </button>
              <button
                onClick={() => fetchBookingData(filters)}
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

      {/* Booking Table */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-200">
        <div className="px-6 py-4 border-b border-gray-200">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-semibold text-gray-900">
              รายการจอง ({bookingData?.total || 0})
            </h2>
            <span className="text-sm text-gray-500">
              อัพเดทล่าสุด: {formatDateTime(bookingData?.generated_at)}
            </span>
          </div>
        </div>

        <div className="overflow-x-auto">
          {loading ? (
            <div className="text-center py-12">
              <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i className="fas fa-spinner animate-spin text-2xl text-gray-400"></i>
              </div>
              <p className="text-gray-500 font-medium">กำลังโหลดข้อมูล...</p>
            </div>
          ) : bookingData?.bookings?.length === 0 ? (
            <div className="text-center py-12">
              <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i className="fas fa-inbox text-2xl text-gray-400"></i>
              </div>
              <p className="text-gray-500 font-medium">ไม่พบข้อมูล</p>
              <p className="text-sm text-gray-400 mt-1">
                ลองปรับเงื่อนไขการค้นหา
              </p>
            </div>
          ) : (
            <table className="w-full">
              <thead className="bg-gray-50 text-nowrap">
                <tr>
                  {visibleColumns.bookingRef && (
                    <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                      Booking Ref
                    </th>
                  )}
                  {visibleColumns.status && (
                    <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                      Status
                    </th>
                  )}
                  {visibleColumns.bookingType && (
                    <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                      Type
                    </th>
                  )}
                  {visibleColumns.passenger && (
                    <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                      Passenger
                    </th>
                  )}
                  {visibleColumns.pax && (
                    <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                      Pax
                    </th>
                  )}
                  {visibleColumns.pickupTime && (
                    <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                      Pickup Time
                    </th>
                  )}
                  {visibleColumns.pickupLocation && (
                    <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                      From
                    </th>
                  )}
                  {visibleColumns.dropoffLocation && (
                    <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                      To
                    </th>
                  )}
                  {visibleColumns.flightNumber && (
                    <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                      Flight
                    </th>
                  )}
                  {visibleColumns.vehicle && (
                    <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                      Vehicle
                    </th>
                  )}
                  {visibleColumns.province && (
                    <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                      Province
                    </th>
                  )}
                  {visibleColumns.resort && (
                    <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                      Resort
                    </th>
                  )}
                  {visibleColumns.driver && (
                    <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                      Driver
                    </th>
                  )}
                  {visibleColumns.vehicleNumber && (
                    <th className="text-left py-3 px-4 font-medium text-gray-700 border-b">
                      Vehicle No.
                    </th>
                  )}
                </tr>
              </thead>
              <tbody className="text-nowrap">
                {currentRecords && currentRecords.length > 0 ? (
                  currentRecords.map((booking) => (
                    <tr
                      key={booking.booking_ref}
                      className="border-b border-gray-100 hover:bg-gray-50 transition-colors"
                    >
                      {visibleColumns.bookingRef && (
                        <td className="py-3 px-4 whitespace-nowrap">
                          <a
                            href={`#booking/${booking.booking_ref}?from=overview`}
                            className={`${getCompanyClass(
                              "text"
                            )} hover:underline font-medium cursor-pointer`}
                            onClick={(e) => {
                              if (e.button === 0 && !e.ctrlKey && !e.metaKey) {
                                e.preventDefault();
                                handleViewBooking(booking.booking_ref);
                              }
                            }}
                          >
                            {booking.booking_ref}
                          </a>
                        </td>
                      )}
                      {visibleColumns.status && (
                        <td className="py-3 px-4">
                          {getStatusBadge(booking.ht_status)}
                        </td>
                      )}
                      {visibleColumns.bookingType && (
                        <td className="py-3 px-4 text-sm">
                          {(() => {
                            const bookingType = getBookingType(booking);
                            return (
                              <div
                                className="flex items-center gap-1"
                                title={bookingType.label}
                              >
                                <i
                                  className={`fas ${bookingType.icon} ${bookingType.color} text-xs`}
                                ></i>
                                <span
                                  className={`text-xs ${bookingType.color} font-medium`}
                                >
                                  {bookingType.label}
                                </span>
                              </div>
                            );
                          })()}
                        </td>
                      )}
                      {visibleColumns.passenger && (
                        <td className="py-3 px-4">
                          <div>
                            <p
                              className="font-normal text-gray-900"
                              title={booking.lead_passenger || ""}
                            >
                              {booking.lead_passenger
                                ? booking.lead_passenger.length > 20
                                  ? booking.lead_passenger.substring(0, 20) +
                                    "..."
                                  : booking.lead_passenger
                                : "-"}
                            </p>
                          </div>
                        </td>
                      )}
                      {visibleColumns.pax && (
                        <td className="py-3 px-4 text-center">
                          <span className="text-sm font-medium text-gray-900">
                            {booking.pax_total}
                          </span>
                        </td>
                      )}
                      {visibleColumns.pickupTime && (
                        <td className="py-3 px-4 text-sm text-gray-600">
                          {formatDateTime(booking.pickup_date)}
                        </td>
                      )}
                      {visibleColumns.pickupLocation && (
                        <td className="py-3 px-4 text-sm text-gray-600">
                          {booking.pickup_location || "-"}
                        </td>
                      )}
                      {visibleColumns.dropoffLocation && (
                        <td className="py-3 px-4 text-sm text-gray-600">
                          {booking.dropoff_location || "-"}
                        </td>
                      )}
                      {visibleColumns.flightNumber && (
                        <td className="py-3 px-4 text-sm text-gray-600">
                          {booking.flight_number || "-"}
                        </td>
                      )}
                      {visibleColumns.vehicle && (
                        <td className="py-3 px-4 whitespace-nowrap">
                          <span className="text-sm text-gray-900">
                            {booking.vehicle_type
                              ? (() => {
                                  const cleanedVehicle = booking.vehicle_type
                                    .replace(/^Private\s+/, "")
                                    .replace(/^Shared\s+/, "")
                                    .trim();
                                  return cleanedVehicle.length > 25
                                    ? cleanedVehicle.substring(0, 25) + "..."
                                    : cleanedVehicle;
                                })()
                              : "-"}
                          </span>
                        </td>
                      )}
                      {visibleColumns.province && (
                        <td className="py-3 px-4 whitespace-nowrap">
                          <span className="text-sm text-gray-900">
                            {booking.province || "Unknown"}
                          </span>
                        </td>
                      )}
                      {visibleColumns.resort && (
                        <td className="py-3 px-4 text-sm text-gray-600">
                          {booking.resort || "-"}
                        </td>
                      )}
                      {visibleColumns.driver && (
                        <td className="py-3 px-4 text-sm text-gray-600">
                          {booking.driver_name || "-"}
                        </td>
                      )}
                      {visibleColumns.vehicleNumber && (
                        <td className="py-3 px-4 text-sm text-gray-600">
                          {booking.vehicle_number || "-"}
                        </td>
                      )}
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td
                      colSpan="13"
                      className="px-6 py-12 text-center text-gray-400"
                    >
                      <i className="fas fa-inbox text-xl mb-4 block"></i>
                      ไม่พบข้อมูล
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          )}
        </div>

        {/* Pagination */}
        {totalPages > 1 && (
          <div className="px-6 py-4 border-t border-gray-200">
            <div className="flex items-center justify-between">
              <div className="text-sm text-gray-700">
                Showing {indexOfFirstRecord + 1} to{" "}
                {Math.min(indexOfLastRecord, bookingData?.total || 0)} of{" "}
                {bookingData?.total || 0} bookings
              </div>

              <div className="flex items-center space-x-2">
                {/* Previous Button */}
                <button
                  onClick={() => handlePageChange(filters.page - 1)}
                  disabled={filters.page === 1}
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
                        filters.page === pageNumber
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
                  onClick={() => handlePageChange(filters.page + 1)}
                  disabled={filters.page === totalPages}
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
    </div>
  );
}

export default BookingOverviewPage;
