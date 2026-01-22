// src/components/pages/JobAssignmentsPage.jsx
import React, {
  useState,
  useEffect,
  useContext,
  useCallback,
  useRef,
  useMemo,
} from "react";
import { getCompanyClass } from "../../config/company";
import { statusUtils } from "../../services/backendApi";
import { BookingContext } from "../../App";
import * as XLSX from "xlsx";
import { Clock, UserCog, MapPin } from "lucide-react";
import ChangeTimeModal from "../modals/ChangeTimeModal";
import EditDriverModal from "../modals/EditDriverModal";
import ChangeTrackingStatusModal from "../modals/ChangeTrackingStatusModal";

function JobAssignmentsPage() {
  const { setSelectedBookingRef, setCurrentPage: setAppPage } =
    useContext(BookingContext);

  const [assignments, setAssignments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [expandedRows, setExpandedRows] = useState(new Set());
  const [provinces, setProvinces] = useState([]);

  // Resort list state
  const [resorts, setResorts] = useState([]);
  const [resortSearchTerm, setResortSearchTerm] = useState("");
  const [showResortDropdown, setShowResortDropdown] = useState(false);

  // Ref for click outside detection
  const driverDropdownRef = useRef(null);
  const resortDropdownRef = useRef(null);

  // Filter states - Load from localStorage if available
  const getInitialFilters = () => {
    try {
      const savedFilters = localStorage.getItem("jobAssignmentsFilters");
      if (savedFilters) {
        return JSON.parse(savedFilters);
      }
    } catch (error) {
      console.error("Error loading saved filters:", error);
    }
    // Default filters - Set dateFrom to today (Thailand timezone)
    const now = new Date();
    const thailandTime = new Date(now.getTime() + 7 * 60 * 60 * 1000); // UTC+7
    const today = thailandTime.toISOString().split("T")[0];
    return {
      province: "all",
      resort: "all",
      dateFrom: today,
      dateTo: "",
      search: "",
    };
  };

  const [filters, setFilters] = useState(getInitialFilters());

  // Column visibility states
  const [visibleColumns, setVisibleColumns] = useState({
    passenger: false,
    vehicle: false,
    phone: false,
    flight: false,
  });

  const toggleColumn = (columnName) => {
    setVisibleColumns((prev) => ({
      ...prev,
      [columnName]: !prev[columnName],
    }));
  };

  // Change Time Modal states
  const [showChangeTimeModal, setShowChangeTimeModal] = useState(false);
  const [selectedAssignment, setSelectedAssignment] = useState(null);

  // Edit Driver Modal states
  const [showEditDriverModal, setShowEditDriverModal] = useState(false);
  const [editingAssignment, setEditingAssignment] = useState(null);

  // Change Tracking Status Modal states
  const [showChangeStatusModal, setShowChangeStatusModal] = useState(false);
  const [statusChangeAssignment, setStatusChangeAssignment] = useState(null);

  // Send Jobs Modal states
  const [showSendModal, setShowSendModal] = useState(false);
  const [drivers, setDrivers] = useState([]);
  const [selectedDriver, setSelectedDriver] = useState(null);
  const [driverSearchTerm, setDriverSearchTerm] = useState("");
  const [showDriverDropdown, setShowDriverDropdown] = useState(false);
  const [generatedMessage, setGeneratedMessage] = useState("");
  const [messageCopied, setMessageCopied] = useState(false);
  const [includeLink, setIncludeLink] = useState(false); // Toggle for including tracking links

  // Filter drivers based on search
  const filteredDrivers = drivers.filter(
    (d) =>
      (d.name || "").toLowerCase().includes(driverSearchTerm.toLowerCase()) ||
      (d.phone_number || "").includes(driverSearchTerm)
  );

  // Filter resorts based on search term
  const filteredResorts = useMemo(() => {
    if (!resortSearchTerm.trim()) {
      return resorts;
    }
    const searchLower = resortSearchTerm.toLowerCase();
    return resorts.filter((resort) =>
      resort.name.toLowerCase().includes(searchLower)
    );
  }, [resorts, resortSearchTerm]);

  // Handle resort search input
  const handleResortSearchChange = (value) => {
    setResortSearchTerm(value);
    setShowResortDropdown(true);
  };

  // Handle resort selection
  const handleResortSelect = (resort) => {
    handleFilterChange("resort", resort);
    setResortSearchTerm(resort);
    setShowResortDropdown(false);
  };

  // Handle resort input focus
  const handleResortFocus = () => {
    setShowResortDropdown(true);
  };

  // Fetch provinces, resorts, and drivers on mount
  useEffect(() => {
    fetchProvinces();
    fetchDrivers();
  }, []);

  // Fetch resorts when other filters change
  useEffect(() => {
    fetchResorts();
  }, [filters.province, filters.dateFrom, filters.dateTo, filters.search]);

  // Fetch assignments when filters change
  useEffect(() => {
    fetchAssignments();
  }, [filters]);

  // Handle click outside to close dropdowns
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (
        driverDropdownRef.current &&
        !driverDropdownRef.current.contains(event.target)
      ) {
        setShowDriverDropdown(false);
      }
      if (
        resortDropdownRef.current &&
        !resortDropdownRef.current.contains(event.target)
      ) {
        setShowResortDropdown(false);
      }
    };

    if (showDriverDropdown || showResortDropdown) {
      document.addEventListener("mousedown", handleClickOutside);
      return () => {
        document.removeEventListener("mousedown", handleClickOutside);
      };
    }
  }, [showDriverDropdown, showResortDropdown]);

  const fetchProvinces = async () => {
    try {
      const response = await fetch("/api/bookings/provinces.php");
      const data = await response.json();
      if (data.success) {
        setProvinces(data.data);
      }
    } catch (error) {
      console.error("Error fetching provinces:", error);
    }
  };

  const fetchDrivers = async () => {
    try {
      const response = await fetch("/api/drivers/list.php");
      const data = await response.json();
      if (data.success) {
        setDrivers(data.data);
      }
    } catch (error) {
      console.error("Error fetching drivers:", error);
    }
  };

  const fetchResorts = async () => {
    try {
      // Build query params (same as assignments API but for resorts)
      const params = new URLSearchParams();

      // IMPORTANT: Use 'assignments' source to only get resorts from assigned bookings
      params.append("source", "assignments");

      // Province
      if (filters.province && filters.province !== "all") {
        params.append("province", filters.province);
      }

      // Date filters
      if (filters.dateFrom) params.append("date_from", filters.dateFrom);
      if (filters.dateTo) params.append("date_to", filters.dateTo);

      // Search
      if (filters.search) params.append("search", filters.search);

      const response = await fetch(`/api/bookings/resorts.php?${params}`);
      const data = await response.json();
      if (data.success) {
        setResorts(data.data);
      }
    } catch (error) {
      console.error("Error fetching resorts:", error);
    }
  };

  const fetchAssignments = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);

      const params = new URLSearchParams({
        limit: 500,
        province: filters.province,
        resort: filters.resort || "all",
      });

      if (filters.search.trim()) {
        params.append("search", filters.search.trim());
      }

      if (filters.dateFrom) {
        params.append("date_from", filters.dateFrom);
      }

      if (filters.dateTo) {
        params.append("date_to", filters.dateTo);
      }

      const response = await fetch(`/api/assignments/list.php?${params}`);
      const data = await response.json();

      if (data.success) {
        setAssignments(data.data.assignments);
      } else {
        setError(data.message || "Failed to load assignments");
      }
    } catch (err) {
      console.error("Error fetching assignments:", err);
      setError("Network error. Please try again.");
    } finally {
      setLoading(false);
    }
  }, [filters]);

  const handleFilterChange = (key, value) => {
    setFilters((prev) => ({ ...prev, [key]: value }));
  };

  // Handle clear filters
  const handleClearFilters = () => {
    const now = new Date();
    const thailandTime = new Date(now.getTime() + 7 * 60 * 60 * 1000); // UTC+7
    const today = thailandTime.toISOString().split("T")[0];
    const defaultFilters = {
      province: "all",
      resort: "all",
      dateFrom: today,
      dateTo: "",
      search: "",
    };
    setFilters(defaultFilters);
    setResortSearchTerm("");
    localStorage.removeItem("jobAssignmentsFilters");
  };

  // Save filters to localStorage whenever they change
  useEffect(() => {
    try {
      localStorage.setItem("jobAssignmentsFilters", JSON.stringify(filters));
    } catch (error) {
      console.error("Error saving filters:", error);
    }
  }, [filters]);

  const toggleRowExpansion = (assignmentId) => {
    const newExpanded = new Set(expandedRows);
    if (newExpanded.has(assignmentId)) {
      newExpanded.delete(assignmentId);
    } else {
      newExpanded.add(assignmentId);
    }
    setExpandedRows(newExpanded);
  };

  // Export to Excel with date grouping
  const handleExportExcel = () => {
    if (assignments.length === 0) {
      alert("No data to export");
      return;
    }

    // Group assignments by date
    const groupedByDate = {};
    assignments.forEach((assignment) => {
      const pickupDate = assignment.booking.pickup_date;
      if (!pickupDate) return;

      const dateKey = new Date(pickupDate).toLocaleDateString("en-GB");

      if (!groupedByDate[dateKey]) {
        groupedByDate[dateKey] = [];
      }

      groupedByDate[dateKey].push(assignment);
    });

    // Create Excel data
    const excelData = [];

    // Sort dates
    const sortedDates = Object.keys(groupedByDate).sort((a, b) => {
      const [dayA, monthA, yearA] = a.split("/");
      const [dayB, monthB, yearB] = b.split("/");
      return (
        new Date(yearA, monthA - 1, dayA) - new Date(yearB, monthB - 1, dayB)
      );
    });

    sortedDates.forEach((date) => {
      // Add date header
      excelData.push({
        Date: date,
        "Booking Ref": "",
        Passenger: "",
        Driver: "",
        Vehicle: "",
        Route: "",
        "Pickup Time": "",
        Resort: "",
      });

      // Add assignments for this date
      groupedByDate[date].forEach((assignment) => {
        const pickupTime = assignment.booking.pickup_date
          ? new Date(assignment.booking.pickup_date).toLocaleTimeString(
              "en-GB",
              {
                hour: "2-digit",
                minute: "2-digit",
              }
            )
          : "-";

        excelData.push({
          Date: "",
          "Booking Ref": assignment.booking_ref,
          Passenger: `${assignment.booking.passenger_name} (${assignment.booking.pax} pax)`,
          Driver: `${assignment.driver.name} (${assignment.driver.phone})`,
          Vehicle: assignment.vehicle.registration,
          Route: `${assignment.booking.pickup_location} → ${assignment.booking.dropoff_location}`,
          "Pickup Time": pickupTime,
          Resort: assignment.booking.resort || assignment.booking.accommodation?.name || "Unknown",
        });
      });

      // Add empty row between dates
      excelData.push({
        Date: "",
        "Booking Ref": "",
        Passenger: "",
        Driver: "",
        Vehicle: "",
        Route: "",
        "Pickup Time": "",
        Resort: "",
      });
    });

    // Create workbook
    const ws = XLSX.utils.json_to_sheet(excelData);

    // Set column widths
    ws["!cols"] = [
      { wch: 12 }, // Date
      { wch: 15 }, // Booking Ref
      { wch: 25 }, // Passenger
      { wch: 25 }, // Driver
      { wch: 12 }, // Vehicle
      { wch: 40 }, // Route
      { wch: 12 }, // Pickup Time
      { wch: 25 }, // Resort
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Job Assignments");

    // Generate filename with date range
    const dateRange =
      filters.dateFrom && filters.dateTo
        ? `${filters.dateFrom}_to_${filters.dateTo}`
        : "all";
    const filename = `Job_Assignments_${dateRange}.xlsx`;

    // Download
    XLSX.writeFile(wb, filename);
  };

  // Print view - Create custom print window
  const handlePrint = () => {
    if (assignments.length === 0) {
      alert("No data to print");
      return;
    }

    // Build HTML for print
    const printHeader = `
      <div style="text-align: center; margin-bottom: 12px;">
        <h2 style="margin: 0 0 5px 0; font-size: 16pt;">Job Assignments Report</h2>
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
          Generated: ${new Date().toLocaleString("en-GB")}
        </p>
      </div>
    `;

    const tableRows = assignments
      .map((assignment) => {
        const pickupTime = assignment.booking.pickup_date
          ? new Date(assignment.booking.pickup_date).toLocaleTimeString(
              "en-GB",
              { hour: "2-digit", minute: "2-digit" }
            )
          : "-";
        const pickupDate = assignment.booking.pickup_date
          ? new Date(assignment.booking.pickup_date).toLocaleDateString("en-GB")
          : "-";

        // Get flight number
        const flightNo =
          assignment.booking.flight_no_arrival ||
          assignment.booking.flight_no_departure ||
          "-";

        return `
        <tr>
          <td style="padding: 5px; border: 1px solid #ccc; font-size: 9pt;">${
            assignment.booking_ref
          }</td>
          <td style="padding: 5px; border: 1px solid #ccc; font-size: 9pt;">
            ${assignment.booking.passenger_name}<br>
            <span style="font-size: 8pt; color: #666;">${
              assignment.booking.pax
            } pax</span>
          </td>
          <td style="padding: 5px; border: 1px solid #ccc; font-size: 9pt;">${
            assignment.booking.resort || assignment.booking.accommodation?.name || "Unknown"
          }</td>
          <td style="padding: 5px; border: 1px solid #ccc; font-size: 9pt;">${
            assignment.driver.name
          }</td>
          <td style="padding: 5px; border: 1px solid #ccc; font-size: 9pt; text-align: center;">${
            assignment.vehicle.registration
          }</td>
          <td style="padding: 5px; border: 1px solid #ccc; font-size: 9pt;">
            ${assignment.booking.pickup_location}<br>
            <span style="color: #666; font-size: 8pt;">→</span><br>
            ${assignment.booking.dropoff_location}
          </td>
          <td style="padding: 5px; border: 1px solid #ccc; font-size: 9pt; text-align: center;">${flightNo}</td>
          <td style="padding: 5px; border: 1px solid #ccc; font-size: 9pt; white-space: nowrap;">
            <strong>${pickupTime}</strong><br>
            <span style="font-size: 8pt; color: #666;">${pickupDate}</span>
          </td>
        </tr>
      `;
      })
      .join("");

    const printContent = `
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="UTF-8">
        <title>Job Assignments Report</title>
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
            text-align: center;
          }

          td {
            vertical-align: top;
            word-wrap: break-word;
          }

          /* ============================================
             COLUMN WIDTHS - ปรับความกว้างคอลัมน์ได้ที่นี่
             รวมต้องได้ 100%
             ============================================ */
          col:nth-child(1) { width: 8%; }  /* Booking Ref */
          col:nth-child(2) { width: 14%; } /* Passenger */
          col:nth-child(3) { width: 12%; }  /* Resort */
          col:nth-child(4) { width: 11%; } /* Driver */
          col:nth-child(5) { width: 9%; }  /* Vehicle */
          col:nth-child(6) { width: 27%; } /* Route */
          col:nth-child(7) { width: 9%; } /* Flight */
          col:nth-child(8) { width: 10%; } /* Pickup Date/Time */
        </style>
      </head>
      <body>
        ${printHeader}
        <table>
          <colgroup>
            <col><col><col><col><col><col><col><col>
          </colgroup>
          <thead>
            <tr>
              <th>Booking Ref</th>
              <th>Passenger</th>
              <th>Resort</th>
              <th>Driver</th>
              <th>Vehicle</th>
              <th>Route</th>
              <th>Flight</th>
              <th>Pickup Date/Time</th>
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
  };

  // Send Jobs to Driver functions
  const handleOpenSendModal = () => {
    setShowSendModal(true);
    setSelectedDriver(null);
    setDriverSearchTerm("");
    setShowDriverDropdown(false);
    setGeneratedMessage("");
    setMessageCopied(false);
    setIncludeLink(false); // Reset to default (no link)
  };

  const handleCloseSendModal = () => {
    setShowSendModal(false);
    setSelectedDriver(null);
    setDriverSearchTerm("");
    setShowDriverDropdown(false);
    setGeneratedMessage("");
    setMessageCopied(false);
    setIncludeLink(false);
  };

  const handleSelectDriver = (driver) => {
    setSelectedDriver(driver);
    setDriverSearchTerm(driver.name || "");
    setShowDriverDropdown(false);
    // Clear generated message when changing driver
    setGeneratedMessage("");
    setMessageCopied(false);
  };

  const handleGenerateMessage = async () => {
    if (!selectedDriver) {
      alert("กรุณาเลือกคนขับ");
      return;
    }

    // DEBUG: Log ALL assignments before filtering
    console.log("🔍 DEBUG: Total assignments from API:", assignments.length);
    console.log("🔍 DEBUG: Selected driver ID:", selectedDriver.id);
    console.log("🔍 DEBUG: Date filter:", filters.dateFrom);

    const allDriverAssignments = assignments.filter(
      (a) => a.driver.id === selectedDriver.id
    );
    console.log(
      "🔍 DEBUG: All assignments for this driver (before date filter):",
      allDriverAssignments.length
    );
    console.log(
      "🔍 DEBUG: All driver assignments:",
      allDriverAssignments.map((a) => {
        let pickupDateFormatted = "NO DATE";
        if (a.booking.pickup_date) {
          const d = new Date(a.booking.pickup_date);
          pickupDateFormatted = `${d.getFullYear()}-${String(
            d.getMonth() + 1
          ).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
        }
        return {
          ref: a.booking_ref,
          passenger: a.booking.passenger_name,
          pickup_date: a.booking.pickup_date,
          pickup_date_formatted: pickupDateFormatted,
        };
      })
    );

    // Filter assignments for selected driver and date
    const driverAssignments = assignments.filter((a) => {
      const matchDriver = a.driver.id === selectedDriver.id;
      // If date filter is set, use it
      if (filters.dateFrom) {
        if (!a.booking.pickup_date) return false;

        // Use local date instead of ISO to avoid timezone issues
        const pickupDateObj = new Date(a.booking.pickup_date);
        const year = pickupDateObj.getFullYear();
        const month = String(pickupDateObj.getMonth() + 1).padStart(2, "0");
        const day = String(pickupDateObj.getDate()).padStart(2, "0");
        const pickupDate = `${year}-${month}-${day}`;

        return matchDriver && pickupDate === filters.dateFrom;
      }
      return matchDriver;
    });

    // DEBUG: Log filtered assignments
    console.log(
      "🔍 DEBUG: Total assignments after date filter:",
      driverAssignments.length
    );
    console.log(
      "🔍 DEBUG: Assignments:",
      driverAssignments.map((a) => ({
        ref: a.booking_ref,
        passenger: a.booking.passenger_name,
        pickup_date: a.booking.pickup_date,
        has_tracking: a.tracking.has_tracking,
        token: a.tracking.token ? "YES" : "NO",
        is_expired: a.tracking.is_expired,
        status: a.tracking.status,
      }))
    );

    if (driverAssignments.length === 0) {
      alert("ไม่พบงานของคนขับในวันที่เลือก");
      return;
    }

    // Check for missing booking data
    const missingData = driverAssignments.filter(
      (a) =>
        !a.booking.pickup_date ||
        !a.booking.pickup_location ||
        !a.booking.dropoff_location
    );
    if (missingData.length > 0) {
      const refs = missingData.map((a) => a.booking_ref).join(", ");
      if (
        !confirm(
          `⚠️ พบข้อมูล booking ไม่ครบสำหรับ: ${refs}\n\nต้องการดำเนินการต่อหรือไม่?`
        )
      ) {
        return;
      }
    }

    // Check and generate tracking tokens ONLY if includeLink is true
    const assignmentsNeedingTokens = includeLink
      ? driverAssignments.filter(
          (a) =>
            !a.tracking.has_tracking ||
            !a.tracking.token ||
            a.tracking.is_expired
        )
      : [];

    if (includeLink && assignmentsNeedingTokens.length > 0) {
      const expiredCount = assignmentsNeedingTokens.filter(
        (a) => a.tracking.is_expired
      ).length;
      const noTrackingCount = assignmentsNeedingTokens.length - expiredCount;

      let generateMessage = `กำลังสร้างลิงก์ tracking...\n`;
      if (expiredCount > 0) {
        generateMessage += `- ลิงก์หมดอายุ: ${expiredCount} งาน\n`;
      }
      if (noTrackingCount > 0) {
        generateMessage += `- ยังไม่มีลิงก์: ${noTrackingCount} งาน`;
      }
      console.log(generateMessage);

      // Generate tokens in parallel
      const tokenPromises = assignmentsNeedingTokens.map(async (assignment) => {
        try {
          const response = await fetch("/api/assignments/generate-link.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              booking_ref: assignment.booking_ref,
              assignment_id: assignment.id,
            }),
          });

          const data = await response.json();
          if (data.success) {
            // Update assignment with new token
            assignment.tracking.has_tracking = true;
            assignment.tracking.token = data.data.token;
            return { success: true, ref: assignment.booking_ref };
          } else {
            return {
              success: false,
              ref: assignment.booking_ref,
              error: data.message,
            };
          }
        } catch (error) {
          return {
            success: false,
            ref: assignment.booking_ref,
            error: error.message,
          };
        }
      });

      const results = await Promise.all(tokenPromises);
      const failed = results.filter((r) => !r.success);

      if (failed.length > 0) {
        const failedRefs = failed
          .map((r) => `${r.ref} (${r.error})`)
          .join("\n");
        alert(
          `⚠️ ไม่สามารถสร้างลิงก์สำหรับบางงาน:\n${failedRefs}\n\nงานเหล่านี้จะไม่มีลิงก์ในข้อความ`
        );
      }
    }

    // Sort by pickup time (earliest first)
    driverAssignments.sort((a, b) => {
      const timeA = a.booking.pickup_date
        ? new Date(a.booking.pickup_date).getTime()
        : 0;
      const timeB = b.booking.pickup_date
        ? new Date(b.booking.pickup_date).getTime()
        : 0;
      return timeA - timeB;
    });

    // DEBUG: Log final assignments that will be in message
    console.log(
      "🔍 DEBUG: Assignments going into message:",
      driverAssignments.length
    );
    console.log(
      "🔍 DEBUG: Message will include:",
      driverAssignments.map((a) => ({
        ref: a.booking_ref,
        passenger: a.booking.passenger_name,
        has_tracking: a.tracking.has_tracking,
        token: a.tracking.token
          ? a.tracking.token.substring(0, 10) + "..."
          : "NO TOKEN",
      }))
    );

    // Generate message with Card Style
    const dateText = filters.dateFrom
      ? new Date(filters.dateFrom).toLocaleDateString("th-TH", {
          year: "numeric",
          month: "long",
          day: "numeric",
        })
      : "ทั้งหมด";

    // Check if assignments span multiple days
    const uniqueDates = new Set(
      driverAssignments
        .map((a) => a.booking.pickup_date)
        .filter((d) => d)
        .map((d) => new Date(d).toLocaleDateString("th-TH"))
    );
    const isMultipleDays = uniqueDates.size > 1;

    let message = `━━━━━━━━━━━━━━━━━━━━\n`;
    message += `🚗 งานรายวัน - คุณ ${selectedDriver.name}\n`;
    message += `📅 ${dateText}\n`;
    if (isMultipleDays) {
      message += `📆 รวม ${uniqueDates.size} วัน\n`;
    }
    message += `━━━━━━━━━━━━━━━━━━━━\n\n`;

    driverAssignments.forEach((assignment, index) => {
      // Use adjusted pickup date if available, otherwise use original
      const effectivePickupDate =
        assignment.booking.pickup_date_adjusted ||
        assignment.booking.pickup_date;
      const isTimeAdjusted = !!assignment.booking.pickup_date_adjusted;
      const pickupDate = effectivePickupDate
        ? new Date(effectivePickupDate)
        : null;

      const pickupTime = pickupDate
        ? pickupDate.toLocaleTimeString("th-TH", {
            hour: "2-digit",
            minute: "2-digit",
          })
        : "-";

      const pickupDateStr = pickupDate
        ? pickupDate.toLocaleDateString("th-TH", {
            day: "numeric",
            month: "short",
          })
        : "-";

      // Show date only if multiple days
      const timeDisplay = isMultipleDays
        ? `${pickupDateStr} ${pickupTime}`
        : pickupTime;

      // Add time adjusted indicator
      const timeWithIndicator = isTimeAdjusted
        ? `${timeDisplay} (เวลาใหม่)`
        : timeDisplay;

      message += `╔════ งาน #${index + 1} - ${timeWithIndicator} ════╗\n`;
      message += `📍 รับ: ${assignment.booking.pickup_location || "ไม่ระบุ"}\n`;
      message += `📍 ส่ง: ${
        assignment.booking.dropoff_location || "ไม่ระบุ"
      }\n`;
      message += `👥 ${assignment.booking.passenger_name || "ไม่ระบุ"} (${
        assignment.booking.pax
      } คน)\n`;

      // Add resort if available (only resort field, not accommodation_name)
      const resort = assignment.booking.resort;
      console.log(`Booking ${assignment.booking_ref} - resort:`, resort); // Debug log
      if (resort) {
        message += `🏨 โซน: ${resort}\n`;
      }

      message += `🔖 ${assignment.booking_ref}\n`;
      message += `\n`;

      // Add tracking link ONLY if includeLink is true
      if (includeLink) {
        if (assignment.tracking.has_tracking && assignment.tracking.token) {
          const trackingUrl = `${window.location.origin}/track.html?token=${assignment.tracking.token}`;
          message += `🔗 กดลิงก์ก่อนเริ่มงาน\n`;
          message += `${trackingUrl}\n`;
        } else {
          message += `⚠️ ไม่มีลิงก์ tracking\n`;
        }
      }
      message += `╚════════════════════════╝\n\n`;
    });

    message += `━━━━━━━━━━━━━━━━━━━━\n`;
    if (includeLink) {
      message += `✅ กรุณาคลิกลิงก์ทุกรอบก่อนเริ่มงาน\n`;
    } else {
      message += `📱 กรุณาเข้าเว็บไซต์คนขับ:\n`;
      message += `https://driver.phuketgevalin.com\n`;
      message += `🔑 ใส่รหัสของคุณเพื่อดูรายละเอียดงาน\n`;
    }
    // message += `📞 ติดปัญหาติดต่อ: 089-xxx-xxxx`;

    setGeneratedMessage(message);
    setMessageCopied(false);
  };

  const handleCopyMessage = () => {
    if (!generatedMessage) {
      return;
    }

    navigator.clipboard
      .writeText(generatedMessage)
      .then(() => {
        setMessageCopied(true);
        setTimeout(() => {
          setMessageCopied(false);
        }, 2000);
      })
      .catch((err) => {
        console.error("Copy failed:", err);
        alert("❌ ไม่สามารถคัดลอกได้");
      });
  };

  const formatDate = (dateString) => {
    if (!dateString) return "-";
    return new Date(dateString).toLocaleDateString("en-GB");
  };

  const formatDateTime = (dateString) => {
    if (!dateString) return "-";
    const date = new Date(dateString);
    return `${date.toLocaleDateString("en-GB")} ${date.toLocaleTimeString(
      "en-GB",
      { hour: "2-digit", minute: "2-digit" }
    )}`;
  };

  const formatTime = (dateString) => {
    if (!dateString) return "";
    const date = new Date(dateString);
    return date.toLocaleTimeString("en-GB", {
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  const getStatusBadge = (status, completionType = null) => {
    // Priority 1: Check completion_type for No Show
    if (completionType === "NO_SHOW") {
      return (
        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
          No Show
        </span>
      );
    }

    // Priority 2: Regular status badges
    const statusConfig = {
      assigned: {
        bg: "bg-green-100",
        text: "text-green-800",
        label: "Assigned",
      },
      completed: {
        bg: "bg-cyan-100",
        text: "text-cyan-800",
        label: "Completed",
      },
      cancelled: { bg: "bg-red-100", text: "text-red-800", label: "Cancelled" },
    };

    const config = statusConfig[status] || {
      bg: "bg-gray-100",
      text: "text-gray-800",
      label: status,
    };

    return (
      <span
        className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${config.bg} ${config.text}`}
      >
        {config.label}
      </span>
    );
  };

  const getTrackingBadge = (tracking) => {
    // Check if token is expired
    if (tracking.has_tracking && tracking.is_expired) {
      return (
        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">
          <i className="fas fa-clock-rotate-left mr-1 text-xs"></i>
          Link Expired
        </span>
      );
    }

    if (!tracking.has_tracking) {
      return (
        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
          <i className="fas fa-link-slash mr-1 text-xs"></i>
          No Tracking
        </span>
      );
    }

    const statusConfig = {
      pending: {
        bg: "bg-yellow-100",
        text: "text-yellow-800",
        icon: "fa-clock",
        label: "Pending Start",
      },
      active: {
        bg: "bg-green-100",
        text: "text-green-800",
        icon: "fa-location-dot",
        label: "Tracking Active",
      },
      completed: {
        bg: "bg-cyan-100",
        text: "text-cyan-800",
        icon: "fa-check-circle",
        label: "Completed",
      },
    };

    const config = statusConfig[tracking.status] || {
      bg: "bg-gray-100",
      text: "text-gray-600",
      icon: "fa-question",
      label: tracking.status,
    };

    return (
      <span
        className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${config.bg} ${config.text}`}
      >
        <i className={`fas ${config.icon} mr-1 text-xs`}></i>
        {config.label}
        {tracking.status === "active" && tracking.total_locations_sent > 0 && (
          <span className="ml-1">({tracking.total_locations_sent})</span>
        )}
      </span>
    );
  };

  return (
    <div className="space-y-6 print:space-y-4">
      {/* Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">
            Job Assignments
          </h1>
          <p className="text-gray-600 mt-1">
            รายการงานที่มอบหมายให้คนขับทั้งหมด
          </p>
        </div>
        <div className="flex gap-2">
          <button
            onClick={handleOpenSendModal}
            disabled={assignments.length === 0}
            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white bg-orange-600 hover:bg-orange-700 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <i className="fas fa-paper-plane"></i>
            ส่งงานให้คนขับ
          </button>
          <button
            onClick={fetchAssignments}
            className={`inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white ${getCompanyClass(
              "primary"
            )} ${getCompanyClass("primaryHover")} shadow-sm`}
          >
            <i className="fas fa-sync-alt"></i>
            Refresh
          </button>
        </div>
      </div>

      {/* Print Header - Only visible when printing */}
      <div className="hidden print:block mb-4">
        <h1 className="text-xl font-bold text-center mb-2">
          Job Assignments Report
        </h1>
        {(filters.dateFrom || filters.dateTo) && (
          <p className="text-center text-sm text-gray-600">
            {filters.dateFrom && filters.dateTo
              ? `${filters.dateFrom} to ${filters.dateTo}`
              : filters.dateFrom
              ? `From ${filters.dateFrom}`
              : `Until ${filters.dateTo}`}
          </p>
        )}
        <p className="text-center text-xs text-gray-500 mt-1">
          Generated: {new Date().toLocaleString("en-GB")}
        </p>
      </div>

      {/* Filters */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-4 print:hidden">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
          {/* Search */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Search (Driver, Ref, Passenger...)
            </label>
            <input
              type="text"
              placeholder="Search driver name, booking ref..."
              value={filters.search}
              onChange={(e) => handleFilterChange("search", e.target.value)}
              className="w-full h-[42px] px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors"
            />
          </div>

          {/* Province Filter */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Province
            </label>
            <select
              value={filters.province}
              onChange={(e) => handleFilterChange("province", e.target.value)}
              className="w-full h-[42px] px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors"
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

          {/* Resort Filter */}
          <div ref={resortDropdownRef} className="relative">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Resort
            </label>
            <div className="relative">
              <input
                type="text"
                value={resortSearchTerm}
                onChange={(e) => handleResortSearchChange(e.target.value)}
                onFocus={handleResortFocus}
                placeholder="Search resort..."
                className="w-full h-[42px] px-3 py-2 pr-10 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors"
              />
              <i className="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            </div>
            {showResortDropdown && (
              <div className="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                <div
                  onClick={() => {
                    handleResortSelect("all");
                    setResortSearchTerm("");
                  }}
                  className="px-3 py-2 hover:bg-gray-100 cursor-pointer text-sm font-medium text-gray-700 border-b border-gray-200"
                >
                  <i className="fas fa-times-circle mr-2 text-gray-400"></i>
                  Clear / All Resorts
                </div>
                {filteredResorts.length > 0 ? (
                  <>
                    {filteredResorts.slice(0, 50).map((resort) => (
                      <div
                        key={resort.name}
                        onClick={() => handleResortSelect(resort.name)}
                        className={`px-3 py-2 hover:bg-cyan-50 cursor-pointer text-sm flex justify-between items-center ${
                          filters.resort === resort.name
                            ? "bg-cyan-50 text-cyan-700 font-medium"
                            : "text-gray-700"
                        }`}
                      >
                        <span>{resort.name}</span>
                        <span className="text-xs text-gray-500 ml-2">
                          {resort.count}
                        </span>
                      </div>
                    ))}
                    {filteredResorts.length > 50 && (
                      <div className="px-3 py-2 text-xs text-gray-500 italic bg-gray-50">
                        + {filteredResorts.length - 50} more... (keep typing to
                        narrow down)
                      </div>
                    )}
                  </>
                ) : (
                  <div className="px-3 py-2 text-sm text-gray-500 italic">
                    No resorts found
                  </div>
                )}
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

        {/* Column Visibility Toggles */}
        <div className="mt-4 pt-4 border-t border-gray-200">
          <div className="flex flex-wrap gap-4">
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={visibleColumns.passenger}
                onChange={() => toggleColumn("passenger")}
                className="w-4 h-4 text-cyan-600 border-gray-300 rounded focus:ring-cyan-500"
              />
              <span className="text-sm text-gray-700">Passenger</span>
            </label>
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={visibleColumns.vehicle}
                onChange={() => toggleColumn("vehicle")}
                className="w-4 h-4 text-cyan-600 border-gray-300 rounded focus:ring-cyan-500"
              />
              <span className="text-sm text-gray-700">Vehicle</span>
            </label>
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={visibleColumns.phone}
                onChange={() => toggleColumn("phone")}
                className="w-4 h-4 text-cyan-600 border-gray-300 rounded focus:ring-cyan-500"
              />
              <span className="text-sm text-gray-700">Driver Phone</span>
            </label>
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={visibleColumns.flight}
                onChange={() => toggleColumn("flight")}
                className="w-4 h-4 text-cyan-600 border-gray-300 rounded focus:ring-cyan-500"
              />
              <span className="text-sm text-gray-700">Flight Number</span>
            </label>
          </div>
        </div>

        {/* Results Summary */}
        <div className="mt-4 pt-4 border-t border-gray-200">
          <div className="flex items-center justify-between text-sm text-gray-600">
            <span>Showing {assignments.length} assignments</span>
            <button
              onClick={handleClearFilters}
              className="px-3 py-1.5 text-sm font-medium rounded-lg text-gray-700 bg-gray-100 hover:bg-gray-200 shadow-sm transition-colors"
              title="Reset all filters to default"
            >
              <i className="fas fa-eraser mr-2"></i>
              Clear Filters
            </button>
          </div>
        </div>
      </div>

      {/* Error Message */}
      {error && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <p className="text-sm text-red-800">{error}</p>
        </div>
      )}

      {/* Assignments Table */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-200 print:shadow-none print:border-0 print:m-0 print:p-0">
        <div className="overflow-x-auto print-container print:overflow-visible">
          {loading ? (
            <div className="text-center py-12">
              <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i className="fas fa-spinner animate-spin text-2xl text-gray-400"></i>
              </div>
              <p className="text-gray-500 font-medium">
                Loading assignments...
              </p>
            </div>
          ) : assignments.length === 0 ? (
            <div className="text-center py-12">
              <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i className="fas fa-clipboard-list text-2xl text-gray-400"></i>
              </div>
              <p className="text-gray-500 font-medium">No assignments found</p>
              <p className="text-sm text-gray-400 mt-1">
                Try adjusting your search criteria
              </p>
            </div>
          ) : (
            <table
              className="w-full print:text-xs print-table"
              style={{ minWidth: "1200px" }}
            >
              <thead className="bg-gray-50 border-b border-gray-200 print:bg-gray-200">
                <tr>
                  <th
                    className="text-center py-2 pl-4 pr-1 font-medium text-gray-700 text-sm"
                    style={{ width: "3%" }}
                  ></th>
                  <th
                    className="text-left py-2 pl-2 pr-2 font-medium text-gray-700 text-sm"
                    style={{ width: "8%" }}
                  >
                    Booking Ref
                  </th>
                  <th
                    className="text-left py-2 px-2 font-medium text-gray-700 text-sm"
                    style={{ width: "5%" }}
                  >
                    Pickup
                  </th>
                  <th
                    className="text-left py-2 px-2 font-medium text-gray-700 text-sm"
                    style={{ width: "5%" }}
                  >
                    Tracking
                  </th>
                  <th
                    className="text-left py-2 px-2 font-medium text-gray-700 text-sm"
                    style={{ width: "10%" }}
                  >
                    Driver
                  </th>
                  {visibleColumns.phone && (
                    <th
                      className="text-left py-2 px-2 font-medium text-gray-700 text-sm"
                      style={{ width: "9%" }}
                    >
                      Phone
                    </th>
                  )}
                  <th
                    className="text-left py-2 px-2 font-medium text-gray-700 text-sm"
                    style={{ width: "12%" }}
                  >
                    Resort
                  </th>
                  <th
                    className="text-left py-2 px-2 font-medium text-gray-700 text-sm"
                    style={{ width: "15%" }}
                  >
                    From
                  </th>
                  <th
                    className="text-left py-2 px-2 font-medium text-gray-700 text-sm"
                    style={{ width: "15%" }}
                  >
                    To
                  </th>
                  {visibleColumns.passenger && (
                    <th
                      className="text-left py-2 px-2 font-medium text-gray-700 text-sm"
                      style={{ width: "12%" }}
                    >
                      Passenger
                    </th>
                  )}
                  {visibleColumns.vehicle && (
                    <th
                      className="text-left py-2 px-2 font-medium text-gray-700 text-sm"
                      style={{ width: "8%" }}
                    >
                      Vehicle
                    </th>
                  )}
                  {visibleColumns.flight && (
                    <th
                      className="text-left py-2 px-2 font-medium text-gray-700 text-sm"
                      style={{ width: "9%" }}
                    >
                      Flight
                    </th>
                  )}
                  <th
                    className="text-center py-2 px-2 pr-4 font-medium text-gray-700 text-sm"
                    style={{ width: "10%" }}
                  >
                    Action
                  </th>
                </tr>
              </thead>

              <tbody>
                {assignments.map((assignment) => {
                  // Determine if this row should have background color
                  const isNoShow = assignment.completion_type === "NO_SHOW";
                  const isCompleted =
                    assignment.tracking.status === "completed" && !isNoShow;

                  let rowBackgroundClass = "";
                  if (isNoShow) {
                    rowBackgroundClass = "bg-red-50"; // Red for No Show
                  } else if (isCompleted) {
                    rowBackgroundClass = "bg-cyan-50"; // cyan for Completed
                  }

                  return (
                    <React.Fragment key={assignment.id}>
                      <tr
                        className={`border-b border-gray-100 hover:bg-gray-50 transition-colors ${rowBackgroundClass}`}
                      >
                        {/* Order Number */}
                        <td className="py-2 pl-4 pr-1 text-sm text-center text-gray-500">
                          {assignments.indexOf(assignment) + 1}
                        </td>

                        {/* Booking Ref */}
                        <td className="py-2 pl-2 pr-2 text-sm">
                          <a
                            href={`#booking/${assignment.booking_ref}?from=assignments`}
                            className={`${getCompanyClass(
                              "text"
                            )} hover:underline font-medium cursor-pointer`}
                            onClick={(e) => {
                              if (e.button === 0 && !e.ctrlKey && !e.metaKey) {
                                e.preventDefault();
                                setSelectedBookingRef({
                                  ref: assignment.booking_ref,
                                  fromPage: "assignments",
                                });
                                setAppPage("booking-detail");
                              }
                            }}
                          >
                            {assignment.booking_ref}
                          </a>
                        </td>

                        {/* Pickup Date/Time */}
                        <td className="py-2 px-2 text-sm">
                          {assignment.booking.pickup_date_adjusted ? (
                            // Show adjusted time with indicator
                            <div>
                              <div className="flex items-center gap-1">
                                <Clock size={12} className="text-orange-600" />
                                <p className="font-semibold text-orange-600">
                                  {formatTime(
                                    assignment.booking.pickup_date_adjusted
                                  )}
                                </p>
                              </div>
                              <p className="text-xs text-gray-600">
                                {formatDate(
                                  assignment.booking.pickup_date_adjusted
                                )}
                              </p>
                              <p className="text-xs text-gray-400 line-through">
                                {formatTime(assignment.booking.pickup_date)}
                              </p>
                            </div>
                          ) : (
                            // Show original time
                            <div>
                              <p className="font-semibold text-gray-900">
                                {formatTime(assignment.booking.pickup_date)}
                              </p>
                              <p className="text-xs text-gray-600">
                                {formatDate(assignment.booking.pickup_date)}
                              </p>
                            </div>
                          )}
                        </td>

                        {/* Tracking */}
                        <td className="py-2 px-2 text-sm text-nowrap">
                          {(() => {
                            // Check if token is expired
                            if (
                              assignment.tracking.has_tracking &&
                              assignment.tracking.is_expired
                            ) {
                              return (
                                <button
                                  onClick={() => {
                                    setStatusChangeAssignment(assignment);
                                    setShowChangeStatusModal(true);
                                  }}
                                  className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800 hover:bg-orange-200 transition-colors cursor-pointer"
                                  title="Click to change status"
                                >
                                  <i className="fas fa-clock-rotate-left mr-1 text-xs"></i>
                                  Link Expired
                                </button>
                              );
                            }

                            if (!assignment.tracking.has_tracking) {
                              return (
                                <button
                                  onClick={() => {
                                    setStatusChangeAssignment(assignment);
                                    setShowChangeStatusModal(true);
                                  }}
                                  className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors cursor-pointer"
                                  title="Click to change status"
                                >
                                  <i className="fas fa-link-slash mr-1 text-xs"></i>
                                  Not Started
                                </button>
                              );
                            }

                            const statusConfig = {
                              pending: {
                                bg: "bg-yellow-100",
                                text: "text-yellow-800",
                                hover: "hover:bg-yellow-200",
                                icon: "fa-clock",
                                label: "Not Started",
                              },
                              active: {
                                bg: "bg-green-100",
                                text: "text-green-800",
                                hover: "hover:bg-green-200",
                                icon: "fa-location-dot",
                                label: "In Progress",
                              },
                              completed: {
                                bg: isNoShow ? "bg-red-100" : "bg-cyan-100",
                                text: isNoShow
                                  ? "text-red-800"
                                  : "text-cyan-800",
                                hover: isNoShow
                                  ? "hover:bg-red-200"
                                  : "hover:bg-cyan-200",
                                icon: isNoShow
                                  ? "fa-user-slash"
                                  : "fa-check-circle",
                                label: isNoShow ? "No Show" : "Completed",
                              },
                            };

                            const config = statusConfig[
                              assignment.tracking.status
                            ] || {
                              bg: "bg-gray-100",
                              text: "text-gray-600",
                              hover: "hover:bg-gray-200",
                              icon: "fa-question",
                              label: assignment.tracking.status,
                            };

                            return (
                              <button
                                onClick={() => {
                                  setStatusChangeAssignment(assignment);
                                  setShowChangeStatusModal(true);
                                }}
                                className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${config.bg} ${config.text} ${config.hover} transition-colors cursor-pointer`}
                                title="Click to change status"
                              >
                                <i
                                  className={`fas ${config.icon} mr-1 text-xs`}
                                ></i>
                                {config.label}
                                {assignment.tracking.status === "active" &&
                                  assignment.tracking.total_locations_sent >
                                    0 && (
                                    <span className="ml-1">
                                      (
                                      {assignment.tracking.total_locations_sent}
                                      )
                                    </span>
                                  )}
                              </button>
                            );
                          })()}
                        </td>

                        {/* Driver */}
                        <td className="py-2 px-2 text-sm">
                          <p
                            className="font-medium text-gray-900 truncate"
                            title={assignment.driver.name}
                          >
                            {assignment.driver.name.length > 15
                              ? assignment.driver.name.substring(0, 15) + "..."
                              : assignment.driver.name}
                          </p>
                        </td>

                        {/* Phone (Optional) */}
                        {visibleColumns.phone && (
                          <td className="py-2 px-2 text-sm">
                            <p className="text-gray-700">
                              {assignment.driver.phone || "-"}
                            </p>
                          </td>
                        )}

                        {/* Resort */}
                        <td className="py-2 px-2 text-sm">
                          <div
                            className="truncate"
                            title={
                              assignment.booking.resort ||
                              assignment.booking.accommodation?.name ||
                              "Unknown"
                            }
                          >
                            {assignment.booking.resort ||
                              assignment.booking.accommodation?.name ||
                              "Unknown"}
                          </div>
                        </td>

                        {/* From */}
                        <td className="py-2 px-2 text-sm">
                          <div
                            className="truncate"
                            title={assignment.booking.pickup_location}
                          >
                            {assignment.booking.pickup_location.length > 25
                              ? assignment.booking.pickup_location.substring(
                                  0,
                                  25
                                ) + "..."
                              : assignment.booking.pickup_location}
                          </div>
                        </td>

                        {/* To */}
                        <td className="py-2 px-2 text-sm">
                          <div
                            className="truncate"
                            title={assignment.booking.dropoff_location}
                          >
                            {assignment.booking.dropoff_location.length > 25
                              ? assignment.booking.dropoff_location.substring(
                                  0,
                                  25
                                ) + "..."
                              : assignment.booking.dropoff_location}
                          </div>
                        </td>

                        {/* Passenger (Optional) */}
                        {visibleColumns.passenger && (
                          <td className="py-2 px-2 text-sm">
                            <p className="font-medium text-gray-900 truncate">
                              {assignment.booking.passenger_name}
                            </p>
                            <p className="text-xs text-gray-600">
                              {assignment.booking.pax} pax
                            </p>
                          </td>
                        )}

                        {/* Vehicle (Optional) */}
                        {visibleColumns.vehicle && (
                          <td className="py-2 px-2 text-sm">
                            <p className="font-medium text-gray-900">
                              {assignment.vehicle.registration || "-"}
                            </p>
                          </td>
                        )}

                        {/* Flight (Optional) */}
                        {visibleColumns.flight && (
                          <td className="py-2 px-2 text-sm">
                            <p className="text-gray-700">
                              {assignment.booking.flight_no_arrival ||
                                assignment.booking.flight_no_departure ||
                                "-"}
                            </p>
                          </td>
                        )}

                        {/* Action */}
                        <td className="py-2 px-2 pr-4">
                          <div className="flex items-center justify-center gap-3">
                            {/* Change Time Button */}
                            <button
                              className="text-cyan-600 hover:text-cyan-700 hover:scale-110 hover:rotate-12 transition-all duration-200 cursor-pointer"
                              title="Change Time"
                              onClick={() => {
                                setSelectedAssignment(assignment);
                                setShowChangeTimeModal(true);
                              }}
                            >
                              <Clock size={18} />
                            </button>

                            {/* Edit Driver Button */}
                            <button
                              className="text-green-600 hover:text-green-700 hover:scale-110 transition-all duration-200 cursor-pointer"
                              title="Edit Driver & Vehicle"
                              onClick={() => {
                                setEditingAssignment(assignment);
                                setShowEditDriverModal(true);
                              }}
                            >
                              <UserCog size={18} />
                            </button>

                            {/* Driver Tracking Link Button */}
                            <button
                              className="text-purple-600 hover:text-purple-700 hover:scale-110 transition-all duration-200 cursor-pointer"
                              title="Open Tracking Link"
                              onClick={() => {
                                if (
                                  assignment.tracking.has_tracking &&
                                  assignment.tracking.token
                                ) {
                                  const trackingUrl = `${window.location.origin}/track.html?token=${assignment.tracking.token}`;
                                  window.open(trackingUrl, "_blank");
                                } else {
                                  alert(
                                    "No tracking link available for this assignment"
                                  );
                                }
                              }}
                            >
                              <MapPin size={18} />
                            </button>
                          </div>
                        </td>
                      </tr>
                    </React.Fragment>
                  );
                })}
              </tbody>
            </table>
          )}
        </div>
      </div>

      {/* Send Jobs Modal */}
      {showSendModal && (
        <div
          className="modal-overlay"
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
          onClick={handleCloseSendModal}
        >
          <div
            className="bg-white rounded-xl shadow-xl"
            style={{
              width: "90%",
              maxWidth: "700px",
              height: "auto",
              maxHeight: "85vh",
              display: "flex",
              flexDirection: "column",
            }}
            onClick={(e) => e.stopPropagation()}
          >
            {/* Modal Header */}
            <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">
                <i className="fas fa-paper-plane text-orange-600 mr-2"></i>
                ส่งงานให้คนขับ
              </h3>
              <button
                onClick={handleCloseSendModal}
                className="text-gray-400 hover:text-gray-600"
              >
                <i className="fas fa-times text-xl"></i>
              </button>
            </div>

            {/* Modal Body */}
            <div
              className="p-6 space-y-4"
              style={{
                flex: 1,
                minHeight: 0,
                overflow: "auto",
                paddingBottom: "220px",
              }}
            >
              {/* Include Link Toggle */}
              <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg border border-gray-200">
                <div className="flex-1">
                  <label className="text-sm font-medium text-gray-900">
                    แนบ Link Tracking
                  </label>
                  <p className="text-xs text-gray-500 mt-1">
                    {includeLink
                      ? "ส่งพร้อม tracking link ให้คนขับคลิกเพื่อเริ่มงาน"
                      : "ส่งข้อความแจ้งเตือนอย่างเดียว ให้คนขับเข้าเว็บด้วย Driver Code"}
                  </p>
                </div>
                <button
                  type="button"
                  onClick={() => {
                    setIncludeLink(!includeLink);
                    // Clear generated message when toggling link option
                    setGeneratedMessage("");
                    setMessageCopied(false);
                  }}
                  className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 ${
                    includeLink ? "bg-orange-600" : "bg-gray-300"
                  }`}
                >
                  <span
                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                      includeLink ? "translate-x-6" : "translate-x-1"
                    }`}
                  />
                </button>
              </div>

              {/* Driver Selection */}
              <div className="relative" ref={driverDropdownRef}>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  เลือกคนขับ *
                </label>

                <input
                  type="text"
                  value={driverSearchTerm}
                  onChange={(e) => {
                    setDriverSearchTerm(e.target.value);
                    setShowDriverDropdown(true);
                    if (!e.target.value) setSelectedDriver(null);
                  }}
                  onFocus={() => setShowDriverDropdown(true)}
                  placeholder="ค้นหาชื่อคนขับหรือเบอร์โทร..."
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                />

                {showDriverDropdown && filteredDrivers.length > 0 && (
                  <div
                    className="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg overflow-y-auto"
                    style={{ maxHeight: "200px" }}
                  >
                    {filteredDrivers.slice(0, 10).map((driver) => (
                      <div
                        key={driver.id}
                        onClick={() => handleSelectDriver(driver)}
                        className="px-4 py-2 hover:bg-orange-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                      >
                        <div className="font-medium text-gray-900">
                          {driver.name || "N/A"}
                        </div>
                        <div className="text-sm text-gray-500">
                          {driver.phone_number || "N/A"}
                        </div>
                      </div>
                    ))}
                  </div>
                )}

                {filters.dateFrom && (
                  <p className="text-xs text-gray-500 mt-2">
                    <i className="fas fa-info-circle mr-1"></i>
                    จะแสดงเฉพาะงานวันที่:{" "}
                    {new Date(filters.dateFrom).toLocaleDateString("th-TH")}
                  </p>
                )}
              </div>

              {/* Generate Message Button */}
              {selectedDriver && !generatedMessage && (
                <button
                  onClick={handleGenerateMessage}
                  className="w-full px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 font-medium"
                >
                  <i className="fas fa-magic mr-2"></i>
                  สร้างข้อความ
                </button>
              )}

              {/* Message Display */}
              {generatedMessage && (
                <div className="bg-cyan-50 border border-cyan-200 rounded-lg p-4">
                  <div className="flex items-center justify-between mb-3">
                    <p className="text-sm text-cyan-900 font-medium">
                      <i className="fas fa-comment-dots mr-2"></i>
                      ข้อความสำหรับส่งให้คนขับ
                    </p>
                    <button
                      onClick={handleCopyMessage}
                      className={`px-4 py-2 text-sm font-medium rounded-lg transition-colors ${
                        messageCopied
                          ? "bg-green-600 hover:bg-green-700 text-white"
                          : "bg-cyan-600 hover:bg-cyan-700 text-white"
                      }`}
                    >
                      <i
                        className={`fas ${
                          messageCopied ? "fa-check" : "fa-copy"
                        } mr-2`}
                      ></i>
                      {messageCopied ? "คัดลอกแล้ว" : "คัดลอกข้อความ"}
                    </button>
                  </div>

                  <div className="bg-white rounded-lg p-4 text-sm text-gray-800 whitespace-pre-wrap break-words border border-cyan-200 font-mono">
                    {generatedMessage}
                  </div>

                  <p className="text-xs text-cyan-700 mt-3">
                    <i className="fas fa-info-circle mr-1"></i>
                    คัดลอกข้อความนี้และส่งให้คนขับผ่าน LINE หรือ WhatsApp
                  </p>
                </div>
              )}
            </div>

            {/* Modal Footer */}
            <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
              <button
                onClick={handleCloseSendModal}
                className="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-white"
              >
                ปิด
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Change Time Modal */}
      <ChangeTimeModal
        isOpen={showChangeTimeModal}
        onClose={() => {
          setShowChangeTimeModal(false);
          setSelectedAssignment(null);
        }}
        assignment={selectedAssignment}
        onSuccess={(data) => {
          // Refresh assignments after successful time change
          fetchAssignments();
        }}
      />

      {/* Edit Driver Modal */}
      <EditDriverModal
        isOpen={showEditDriverModal}
        onClose={() => {
          setShowEditDriverModal(false);
          setEditingAssignment(null);
        }}
        assignment={editingAssignment}
        onSuccess={() => {
          // Refresh assignments after successful driver update
          fetchAssignments();
        }}
      />

      {/* Change Tracking Status Modal */}
      <ChangeTrackingStatusModal
        isOpen={showChangeStatusModal}
        onClose={() => {
          setShowChangeStatusModal(false);
          setStatusChangeAssignment(null);
        }}
        assignment={statusChangeAssignment}
        onSuccess={() => {
          // Refresh assignments after successful status change
          fetchAssignments();
        }}
      />
    </div>
  );
}

export default JobAssignmentsPage;
