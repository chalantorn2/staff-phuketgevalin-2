// src/components/pages/BookingDetailPage.jsx
import { useState, useEffect, useContext, useRef } from "react";
import { backendApi } from "../../services/backendApi";
import { getCompanyClass } from "../../config/company";
import { BookingContext } from "../../App";
import EditProvinceModal from "../modals/EditProvinceModal";

function BookingDetailPage({ bookingRef, onBack, fromPage = "dashboard" }) {
  const [bookingDetail, setBookingDetail] = useState(null);
  const [bookingNotes, setBookingNotes] = useState(null);
  const [apiNotes, setApiNotes] = useState(null);
  const [notesLoading, setNotesLoading] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [assignment, setAssignment] = useState(null);
  const [showAssignModal, setShowAssignModal] = useState(false);
  const [drivers, setDrivers] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const { refreshBookings } = useContext(BookingContext);
  const [driverSearchTerm, setDriverSearchTerm] = useState("");
  const [vehicleSearchTerm, setVehicleSearchTerm] = useState("");
  const [showDriverDropdown, setShowDriverDropdown] = useState(false);
  const [showVehicleDropdown, setShowVehicleDropdown] = useState(false);
  const [trackingLink, setTrackingLink] = useState(null);
  const [showTrackingModal, setShowTrackingModal] = useState(false);
  const [generatingLink, setGeneratingLink] = useState(false);
  const [messageCopied, setMessageCopied] = useState(false);

  // Province edit state
  const [showEditProvinceModal, setShowEditProvinceModal] = useState(false);

  // Refs for click outside detection
  const driverDropdownRef = useRef(null);
  const vehicleDropdownRef = useRef(null);
  const [provinces, setProvinces] = useState([]);

  // Direction swap state (manual override)
  const [isDirectionSwapped, setIsDirectionSwapped] = useState(false);

  // Accordion state
  const [showTechnicalDetails, setShowTechnicalDetails] = useState(false);

  const ref = bookingRef?.ref || bookingRef;

  // Load direction swap state from localStorage
  useEffect(() => {
    if (ref) {
      const saved = localStorage.getItem(`direction_swap_${ref}`);
      setIsDirectionSwapped(saved === "true");
    }
  }, [ref]);

  const filteredDrivers = drivers.filter(
    (d) =>
      (d.name || "").toLowerCase().includes(driverSearchTerm.toLowerCase()) ||
      (d.phone_number || "").includes(driverSearchTerm)
  );

  const filteredVehicles = vehicles.filter(
    (v) =>
      (v.registration || "")
        .toLowerCase()
        .includes(vehicleSearchTerm.toLowerCase()) ||
      (v.brand || "").toLowerCase().includes(vehicleSearchTerm.toLowerCase()) ||
      (v.model || "").toLowerCase().includes(vehicleSearchTerm.toLowerCase())
  );

  const [assignmentForm, setAssignmentForm] = useState({
    driver_id: "",
    vehicle_id: "",
    notes: "",
  });
  const [assignLoading, setAssignLoading] = useState(false);

  // === Assignment Functions ===
  const fetchAssignment = async () => {
    try {
      const response = await fetch(
        `/api/assignments/assign.php?booking_ref=${ref}`
      );
      const data = await response.json();

      if (data.success && data.data) {
        setAssignment(data.data);
      }
    } catch (error) {
      console.error("Error fetching assignment:", error);
    }
  };

  const fetchDriversAndVehicles = async () => {
    try {
      const [driversRes, vehiclesRes] = await Promise.all([
        fetch("/api/drivers/manage.php"),
        fetch("/api/vehicles/manage.php"),
      ]);

      const driversData = await driversRes.json();
      const vehiclesData = await vehiclesRes.json();

      if (driversData.success) {
        setDrivers(driversData.data.filter((d) => d.status === "active"));
      }

      if (vehiclesData.success) {
        setVehicles(vehiclesData.data.filter((v) => v.status === "active"));
      }
    } catch (error) {
      console.error("Error fetching drivers/vehicles:", error);
    }
  };

  const handleDriverChange = (driverId) => {
    const driver = drivers.find((d) => d.id == driverId);
    setAssignmentForm({
      ...assignmentForm,
      driver_id: driverId,
      vehicle_id: driver?.default_vehicle_id || "",
    });
  };

  const handleAssignJob = async () => {
    if (!assignmentForm.driver_id || !assignmentForm.vehicle_id) {
      alert("Please select driver and vehicle");
      return;
    }

    // ตรวจสอบ booking status - อนุญาตให้ ACON และ AAMM assign job ได้
    const booking = bookingDetail?.booking;
    const bookingStatus = booking?.general?.status;
    const allowedStatuses = ["ACON", "AAMM"];
    if (!allowedStatuses.includes(bookingStatus)) {
      alert(
        "Cannot assign job. Only confirmed bookings (ACON) or amendment approved bookings (AAMM) can be assigned."
      );
      return;
    }

    try {
      setAssignLoading(true);

      // Assign job locally
      const response = await fetch("/api/assignments/assign.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          booking_ref: ref,
          ...assignmentForm,
        }),
      });

      const data = await response.json();

      if (data.success) {
        alert("Job assigned successfully!");
        setShowAssignModal(false);
        fetchAssignment();
        fetchBookingDetail(true); // Refresh booking detail from API
        setAssignmentForm({ driver_id: "", vehicle_id: "", notes: "" });

        // ← เพิ่มบรรทัดนี้: Trigger refresh ใน BookingManagementPage
        window.dispatchEvent(new Event("refreshBookings"));
      } else {
        alert(data.message || "Failed to assign job");
      }
    } catch (error) {
      console.error("Error assigning job:", error);
      alert("Error assigning job");
    } finally {
      setAssignLoading(false);
    }
  };

  const handleReassign = async () => {
    if (!assignmentForm.driver_id || !assignmentForm.vehicle_id) {
      alert("Please select driver and vehicle");
      return;
    }

    try {
      setAssignLoading(true);

      // Reassign job locally
      const response = await fetch("/api/assignments/assign.php", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          assignment_id: assignment.id,
          ...assignmentForm,
        }),
      });

      const data = await response.json();

      if (data.success) {
        alert("Job reassigned successfully!");
        setShowAssignModal(false);
        fetchAssignment();
        fetchBookingDetail(true); // Refresh booking detail from API
        setAssignmentForm({ driver_id: "", vehicle_id: "", notes: "" });
        window.dispatchEvent(new Event("refreshBookings"));
      } else {
        alert(data.message || "Failed to reassign job");
      }
    } catch (error) {
      console.error("Error reassigning job:", error);
      alert("Error reassigning job");
    } finally {
      setAssignLoading(false);
    }
  };

  const handleUnassign = async () => {
    if (!confirm("Are you sure you want to unassign this job?")) return;

    try {
      const response = await fetch(
        `/api/assignments/assign.php?id=${assignment.id}`,
        {
          method: "DELETE",
        }
      );

      const data = await response.json();

      if (data.success) {
        alert("Assignment removed successfully!");
        setAssignment(null);
        setShowAssignModal(false);
        setTrackingLink(null);
      } else {
        alert(data.message || "Failed to remove assignment");
      }
    } catch (error) {
      console.error("Error removing assignment:", error);
      alert("Error removing assignment");
    }
  };

  const handleGenerateTrackingLink = async () => {
    if (!assignment) {
      alert("Please assign a job first");
      return;
    }

    console.log("Generating tracking link with:", {
      booking_ref: ref,
      assignment_id: assignment.id,
      assignment: assignment,
    });

    try {
      setGeneratingLink(true);

      const payload = {
        booking_ref: ref,
        assignment_id: assignment.id,
      };

      console.log("Sending payload:", payload);

      const response = await fetch("/api/assignments/generate-link.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      const data = await response.json();
      console.log("API Response:", data);

      if (data.success) {
        setTrackingLink(data.data);
        setMessageCopied(false); // Reset copy state when opening modal
        setShowTrackingModal(true);
      } else {
        alert(data.message || "Failed to generate tracking link");
      }
    } catch (error) {
      console.error("Error generating tracking link:", error);
      alert("Error generating tracking link");
    } finally {
      setGeneratingLink(false);
    }
  };

  const handleCopyLink = () => {
    if (trackingLink?.tracking_url) {
      navigator.clipboard.writeText(trackingLink.tracking_url);
      // alert("Link copied to clipboard!");
    }
  };

  const generateDriverMessage = () => {
    if (!trackingLink || !bookingDetail) return "";

    const booking = bookingDetail.booking;
    const general = booking?.general || {};
    const arrival = booking?.arrival || {};
    const departure = booking?.departure || {};

    // กำหนดข้อมูลตาม booking type + manual swap
    // Check if it's an Arrival transfer:
    // - booking_type contains "arrival" or "outbound" (outbound = going to hotel)
    // - OR has arrival data but no departure data
    const bookingTypeLower = general.bookingtype?.toLowerCase() || "";
    let isArrival =
      bookingTypeLower.includes("arrival") ||
      bookingTypeLower.includes("outbound") ||
      (arrival.arrivaldate &&
        !departure.departuredate &&
        !departure.pickupdate);

    // Apply manual swap
    if (isDirectionSwapped) {
      isArrival = !isArrival;
    }

    // Use adjusted pickup date if available, otherwise use original
    let originalPickupDate, pickupLocation, dropoffLocation, flightNo;

    // Handle Quote bookings separately
    if (general.bookingtype === "Quote") {
      const quote = booking?.quote || {};
      originalPickupDate = quote.transferdate;

      // Apply direction swap for Quote
      if (isDirectionSwapped) {
        pickupLocation = quote.dropoffaddress1 || "Dropoff Location";
        dropoffLocation = quote.pickupaddress1 || "Pickup Location";
      } else {
        pickupLocation = quote.pickupaddress1 || "Pickup Location";
        dropoffLocation = quote.dropoffaddress1 || "Dropoff Location";
      }

      flightNo = arrival.flightno || departure.flightno || null;
    } else {
      // Handle Arrival/Departure bookings
      originalPickupDate = isArrival
        ? arrival.arrivaldate
        : departure.pickupdate || departure.departuredate;
      flightNo = isArrival ? arrival.flightno : departure.flightno;
      pickupLocation = isArrival
        ? general.airport || "Airport"
        : departure.accommodationname ||
          arrival.accommodationname ||
          general.resort ||
          "Hotel";
      dropoffLocation = isArrival
        ? arrival.accommodationname || general.resort || "Hotel"
        : general.airport || "Airport";
    }

    const pickupDate = booking?.pickup_date_adjusted || originalPickupDate;
    const isTimeAdjusted = !!booking?.pickup_date_adjusted;

    // นับจำนวนผู้โดยสาร
    const passengers = [];
    if (general.adults) passengers.push(`${general.adults} Adults`);
    if (general.children) passengers.push(`${general.children} Children`);
    if (general.infants) passengers.push(`${general.infants} Infants`);
    const passengerCount = passengers.join(", ") || "N/A";

    const message = `🚗 งานใหม่จาก Phuket Gevalin

📋 Booking: ${ref}
👤 ชื่อลูกค้า: ${general.passengername || "N/A"}
👥 จำนวน: ${passengerCount}

📅 วันที่รับ: ${formatDateTime(pickupDate)}${
      isTimeAdjusted ? " (เวลาใหม่)" : ""
    }
${flightNo ? `✈️ เที่ยวบิน: ${flightNo}` : ""}
📍 รับที่: ${pickupLocation}
📍 ส่งที่: ${dropoffLocation}

🔗 Tracking Link:
${trackingLink.tracking_url}

📱 วิธีใช้:
1. คลิกลิงก์ด้านบน
2. กด "เริ่มงาน"
3. อนุญาตการเข้าถึง GPS
4. เมื่อเสร็จงานให้กด "เสร็จสิ้นงาน"`;

    return message;
  };

  const handleCopyMessage = () => {
    const message = generateDriverMessage();
    if (message) {
      navigator.clipboard.writeText(message);
      setMessageCopied(true);
    }
  };

  const handleSelectDriver = (driver) => {
    setAssignmentForm({
      ...assignmentForm,
      driver_id: driver.id,
      vehicle_id: driver.default_vehicle_id || assignmentForm.vehicle_id,
    });
    setDriverSearchTerm(
      `${driver.name || "N/A"} (${driver.phone_number || "N/A"})`
    );
    setShowDriverDropdown(false);
  };

  // Handle vehicle selection
  const handleSelectVehicle = (vehicle) => {
    setAssignmentForm({
      ...assignmentForm,
      vehicle_id: vehicle.id,
    });
    setVehicleSearchTerm(
      `${vehicle.registration || "N/A"} - ${vehicle.brand || "N/A"} ${
        vehicle.model || ""
      }`
    );
    setShowVehicleDropdown(false);
  };

  const openAssignModal = () => {
    if (assignment) {
      const driver = drivers.find((d) => d.id == assignment.driver_id);
      const vehicle = vehicles.find((v) => v.id == assignment.vehicle_id);

      setDriverSearchTerm(
        driver ? `${driver.name} (${driver.phone_number})` : ""
      );
      setVehicleSearchTerm(
        vehicle
          ? `${vehicle.registration} - ${vehicle.brand} ${vehicle.model}`
          : ""
      );

      setAssignmentForm({
        driver_id: assignment.driver_id,
        vehicle_id: assignment.vehicle_id,
        notes: assignment.assignment_notes || "",
      });
    } else {
      setDriverSearchTerm("");
      setVehicleSearchTerm("");
      setAssignmentForm({ driver_id: "", vehicle_id: "", notes: "" });
    }

    setShowDriverDropdown(false);
    setShowVehicleDropdown(false);
    setShowAssignModal(true);
  };

  const handleProvinceSaved = () => {
    fetchBookingDetail(); // Refresh booking detail
    setShowEditProvinceModal(false);
  };

  // Fetch provinces list
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

  useEffect(() => {
    if (ref) {
      fetchBookingDetail(true); // Auto-sync from Holiday Taxis API every time
      fetchAssignment();
      fetchDriversAndVehicles();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ref]);

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
        vehicleDropdownRef.current &&
        !vehicleDropdownRef.current.contains(event.target)
      ) {
        setShowVehicleDropdown(false);
      }
    };

    if (showDriverDropdown || showVehicleDropdown) {
      document.addEventListener("mousedown", handleClickOutside);
      return () => {
        document.removeEventListener("mousedown", handleClickOutside);
      };
    }
  }, [showDriverDropdown, showVehicleDropdown]);

  const syncBookingFromAPI = async () => {
    try {
      const apiUrl = `${
        import.meta.env.VITE_API_BASE_URL ||
        "https://staff.phuketgevalin.com/api"
      }/sync/get-booking.php?booking_ref=${ref}`;

      const response = await fetch(apiUrl);
      const data = await response.json();

      if (!data.success) {
        throw new Error(data.error || "Failed to sync booking from API");
      }

      return data;
    } catch (err) {
      console.error("Error syncing booking from API:", err);
      throw err;
    }
  };

  const fetchBookingDetail = async (shouldSync = false) => {
    try {
      setLoading(true);
      setError(null);

      // Step 1: Sync from API if requested
      if (shouldSync) {
        try {
          await syncBookingFromAPI();
        } catch (syncErr) {
          console.error("Sync error:", syncErr);
          // Continue to fetch from DB even if sync fails
        }
      }

      // Step 2: Fetch from Database
      const response = await backendApi.getBookingDetailFromDB(ref);

      if (response.success) {
        setBookingDetail({ booking: response.data });
        setBookingNotes(response.notes);
        fetchNotesFromAPI();
      } else {
        throw new Error(response.error || "Failed to fetch booking detail");
      }
    } catch (err) {
      console.error("Error fetching booking detail:", err);
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const fetchNotesFromAPI = async () => {
    try {
      setNotesLoading(true);
      const notesResponse = await backendApi.holidayTaxis.getBookingNotes(ref);

      if (notesResponse.success && notesResponse.data.notes) {
        const apiNotesData = notesResponse.data.notes;
        if (apiNotesData.note_0) {
          const note = apiNotesData.note_0;
          let noteText = note.note || "";

          if (note.notedate) noteText += `\n\nDate: ${note.notedate}`;
          if (note.user) noteText += `\nUser: ${note.user}`;

          const flags = [];
          if (note.flightnoquery) flags.push("Flight Query");
          if (note.wrongresort) flags.push("Wrong Resort");

          if (flags.length > 0) {
            noteText += `\nFlags: ${flags.join(", ")}`;
          }

          setApiNotes(noteText);
        }
      }
    } catch (error) {
      console.error("Error fetching notes from API:", error);
      setApiNotes("Error loading notes from API");
    } finally {
      setNotesLoading(false);
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return "-";
    const date = new Date(dateString);
    // Check if date is invalid
    if (isNaN(date.getTime())) return "-";
    return date.toLocaleDateString("en-GB");
  };

  const formatDateTime = (dateString) => {
    if (!dateString) return "-";
    const date = new Date(dateString);
    // Check if date is invalid
    if (isNaN(date.getTime())) return "-";
    return `${date.toLocaleDateString("en-GB")} ${date.toLocaleTimeString(
      "en-GB",
      {
        hour: "2-digit",
        minute: "2-digit",
      }
    )}`;
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

  const Section = ({ title, children, right }) => (
    <div className="bg-white rounded-xl border border-gray-200 shadow-sm">
      <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <h2 className="text-base lg:text-lg font-semibold text-gray-900">
          {title}
        </h2>
        {right}
      </div>
      <div className="p-6">{children}</div>
    </div>
  );

  const Row = ({ label, value, span = 1 }) => (
    <div className={`col-span-${span}`}>
      <div className="text-[13px] font-medium text-gray-500">{label}</div>
      <div className="mt-1 text-[15px] text-gray-900">{value ?? "-"}</div>
    </div>
  );

  const SimpleList = ({ items }) => (
    <div className="divide-y divide-gray-100 rounded-lg overflow-hidden border border-gray-100">
      {items.map(({ label, value }, idx) => (
        <div key={idx} className="grid grid-cols-12 gap-4 px-4 py-3 bg-white">
          <div className="col-span-5 md:col-span-4 text-sm text-gray-600">
            {label}
          </div>
          <div className="col-span-7 md:col-span-8 text-sm text-gray-900">
            {value || "-"}
          </div>
        </div>
      ))}
    </div>
  );

  if (!ref) {
    return (
      <div className="space-y-6">
        <div className="text-center py-12">
          <p className="text-gray-500">No booking selected</p>
          <button
            onClick={onBack}
            className="mt-4 px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            <i className="fas fa-arrow-left mr-2"></i>
            Back
          </button>
        </div>
      </div>
    );
  }

  if (loading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center gap-4">
          <button
            onClick={onBack}
            className="px-3 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            <i className="fas fa-arrow-left mr-2"></i>
            Back
          </button>
          <h1 className="text-2xl font-semibold text-gray-900">Loading…</h1>
        </div>

        <Section title="Loading booking details">
          <div className="text-center py-8">
            <p className="text-gray-500 font-medium">
              Please wait while we fetch the booking information.
            </p>
          </div>
        </Section>
      </div>
    );
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div className="flex items-center gap-4">
          <button
            onClick={onBack}
            className="px-3 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            <i className="fas fa-arrow-left mr-2"></i>
            Back
          </button>
          <h1 className="text-2xl font-semibold text-gray-900">Error</h1>
        </div>

        <div className="bg-red-50 border border-red-200 rounded-lg p-6">
          <p className="text-red-800">{error}</p>
          <button
            onClick={fetchBookingDetail}
            className="mt-4 px-4 py-2 text-sm font-medium text-red-700 border border-red-300 rounded-lg hover:bg-red-50"
          >
            Try Again
          </button>
        </div>
      </div>
    );
  }

  const booking = bookingDetail?.booking;
  const general = booking?.general || {};
  const arrival = booking?.arrival || {};
  const departure = booking?.departure || {};

  // Debug: ดูโครงสร้างข้อมูล
  console.log("=== Booking Detail Debug ===");
  console.log("bookingDetail:", bookingDetail);
  console.log("booking:", booking);
  console.log("general:", general);
  console.log("arrival:", arrival);
  console.log("departure:", departure);
  console.log("booking.arrival_date:", booking?.arrival_date);
  console.log("booking.departure_date:", booking?.departure_date);
  console.log("booking.pickup_date:", booking?.pickup_date);

  const statusColor =
    general.status === "PCON"
      ? "bg-cyan-100 text-cyan-800"
      : general.status === "ACON"
      ? "bg-green-100 text-green-800"
      : general.status === "PCAN"
      ? "bg-orange-100 text-orange-800"
      : general.status === "ACAN"
      ? "bg-red-100 text-red-800"
      : general.status === "PAMM"
      ? "bg-yellow-100 text-yellow-800"
      : general.status === "AAMM"
      ? "bg-purple-100 text-purple-800"
      : "bg-gray-100 text-gray-800";

  return (
    <div className="space-y-6">
      <style>{`
        .modal-overlay {
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background-color: rgba(0, 0, 0, 0.5);
          display: flex;
          align-items: center;
          justify-content: center;
          z-index: 9999;
          padding: 1rem;
        }
        .modal-content {
          background: white;
          border-radius: 0.75rem;
          max-width: 32rem;
          width: 100%;
          max-height: 90vh;
          overflow-y: auto;
          box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
      `}</style>

      {/* Header */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <button
            onClick={onBack}
            className="px-3 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            <i className="fas fa-arrow-left mr-2"></i>
            Back
          </button>
          <div>
            <h1 className="text-2xl font-semibold text-gray-900">
              Booking Detail: {ref}
            </h1>
            <p className="text-gray-600 mt-1">รายละเอียดการจองทั้งหมด</p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <span
            className={`px-3 py-1 text-sm font-medium rounded-full ${statusColor}`}
          >
            {getReadableStatus(general.status)}
          </span>

          {/* Assignment Button */}
          <button
            onClick={openAssignModal}
            disabled={
              !assignment &&
              general.status !== "ACON" &&
              general.status !== "AAMM"
            }
            className={`group px-4 py-2 text-sm font-medium rounded-lg text-white ${
              assignment
                ? "bg-green-600 hover:bg-green-700"
                : general.status === "ACON" || general.status === "AAMM"
                ? "bg-yellow-600 hover:bg-yellow-700"
                : "bg-gray-400 cursor-not-allowed"
            } disabled:opacity-50`}
            title={
              !assignment &&
              general.status !== "ACON" &&
              general.status !== "AAMM"
                ? "Only confirmed bookings (ACON) or amendment approved bookings (AAMM) can be assigned"
                : ""
            }
          >
            <i
              className={`fas ${
                assignment ? "fa-user-check" : "fa-user-plus"
              } mr-2`}
            ></i>
            {assignment ? (
              <>
                <span className="group-hover:hidden">Assigned</span>
                <span className="hidden group-hover:inline">Reassign Job</span>
              </>
            ) : (
              <span>Assign Job</span>
            )}
          </button>

          {/* Generate Tracking Link Button */}
          {assignment && (
            <button
              onClick={handleGenerateTrackingLink}
              disabled={generatingLink}
              className="px-4 py-2 text-sm font-medium rounded-lg text-white bg-purple-600 hover:bg-purple-700 disabled:opacity-50"
            >
              {generatingLink ? (
                <>
                  <i className="fas fa-spinner animate-spin mr-2"></i>
                  Generating...
                </>
              ) : (
                <>
                  <i className="fas fa-link mr-2"></i>
                  Tracking Link
                </>
              )}
            </button>
          )}

          <button
            onClick={() => fetchBookingDetail(true)}
            disabled={loading}
            className={`px-4 py-2 text-sm font-medium rounded-lg ${getCompanyClass(
              "primary"
            )} ${getCompanyClass(
              "primaryHover"
            )} text-white disabled:opacity-50`}
            title="Sync from Holiday Taxis API and refresh"
          >
            <i
              className={`fas fa-sync-alt mr-2 ${
                loading ? "animate-spin" : ""
              }`}
            ></i>
            Refresh from API
          </button>
        </div>
      </div>

      {/* Transfer Summary Card */}
      <div className="bg-gradient-to-br from-cyan-50 to-cyan-50 rounded-xl shadow-sm border border-cyan-100 p-6 mb-6">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-2">
            <i className="fas fa-route text-cyan-600 text-lg"></i>
            <h3 className="text-lg font-semibold text-gray-900">
              Transfer Summary
            </h3>
          </div>
          {/* Swap Button */}
          <button
            onClick={() => {
              const newValue = !isDirectionSwapped;
              setIsDirectionSwapped(newValue);
              localStorage.setItem(
                `direction_swap_${ref}`,
                newValue.toString()
              );
            }}
            className={`px-4 py-2 text-sm font-medium rounded-lg transition-all ${
              isDirectionSwapped
                ? "bg-orange-600 text-white hover:bg-orange-700"
                : "bg-white text-gray-700 hover:bg-gray-100 border border-gray-300"
            }`}
            title="สลับสถานที่ From ↔ To"
          >
            <i className="fas fa-exchange-alt mr-2"></i>
            {isDirectionSwapped ? "สถานที่ถูกสลับแล้ว" : "สลับสถานที่"}
          </button>
        </div>

        {/* Route Display */}
        <div className="bg-white rounded-lg p-5">
          <div className="flex items-center gap-4">
            {/* From Location */}
            <div className="flex-1 text-center">
              <div className="inline-flex items-center gap-2 mb-2">
                <div className="w-2.5 h-2.5 bg-green-500 rounded-full"></div>
                <span className="text-xs font-medium text-gray-500">From</span>
              </div>
              <p className="font-semibold text-gray-900 text-sm sm:text-base">
                {(() => {
                  // Quote bookings - Point-to-Point
                  if (general.bookingtype === "Quote") {
                    const quote = bookingDetail?.booking?.quote;
                    return isDirectionSwapped
                      ? quote?.dropoffaddress1 || "Dropoff Location"
                      : quote?.pickupaddress1 || "Pickup Location";
                  }

                  // Airport bookings
                  const bookingTypeLower =
                    general.bookingtype?.toLowerCase() || "";
                  let isArrival =
                    bookingTypeLower.includes("arrival") ||
                    bookingTypeLower.includes("outbound") ||
                    (arrival.arrivaldate &&
                      !departure.departuredate &&
                      !departure.pickupdate);
                  if (isDirectionSwapped) isArrival = !isArrival;

                  return isArrival
                    ? general.airport || arrival.fromairport || "Airport"
                    : departure.accommodationname ||
                        arrival.accommodationname ||
                        general.resort ||
                        "Accommodation";
                })()}
              </p>
              {/* Show city/province for Quote bookings */}
              {general.bookingtype === "Quote" &&
                (() => {
                  const quote = bookingDetail?.booking?.quote;
                  const city = isDirectionSwapped
                    ? quote?.dropoffaddress3 || quote?.dropoffaddress2
                    : quote?.pickupaddress3 || quote?.pickupaddress2;
                  return city ? (
                    <p className="text-xs text-gray-500 mt-1">{city}</p>
                  ) : null;
                })()}
            </div>

            {/* Arrow - Centered */}
            <div className="flex items-center justify-center px-2">
              <i className="fas fa-arrow-right text-cyan-500 text-2xl"></i>
            </div>

            {/* To Location */}
            <div className="flex-1 text-center">
              <div className="inline-flex items-center gap-2 mb-2">
                <div className="w-2.5 h-2.5 bg-red-500 rounded-full"></div>
                <span className="text-xs font-medium text-gray-500">To</span>
              </div>
              <p className="font-semibold text-gray-900 text-sm sm:text-base">
                {(() => {
                  // Quote bookings - Point-to-Point
                  if (general.bookingtype === "Quote") {
                    const quote = bookingDetail?.booking?.quote;
                    return isDirectionSwapped
                      ? quote?.pickupaddress1 || "Pickup Location"
                      : quote?.dropoffaddress1 || "Dropoff Location";
                  }

                  // Airport bookings
                  const bookingTypeLower =
                    general.bookingtype?.toLowerCase() || "";
                  let isArrival =
                    bookingTypeLower.includes("arrival") ||
                    bookingTypeLower.includes("outbound") ||
                    (arrival.arrivaldate &&
                      !departure.departuredate &&
                      !departure.pickupdate);
                  if (isDirectionSwapped) isArrival = !isArrival;

                  return isArrival
                    ? arrival.accommodationname ||
                        general.resort ||
                        "Accommodation"
                    : general.airport || departure.toairport || "Airport";
                })()}
              </p>
              {/* Show city/province for Quote bookings */}
              {general.bookingtype === "Quote" &&
                (() => {
                  const quote = bookingDetail?.booking?.quote;
                  const city = isDirectionSwapped
                    ? quote?.pickupaddress3 || quote?.pickupaddress2
                    : quote?.dropoffaddress3 || quote?.dropoffaddress2;
                  return city ? (
                    <p className="text-xs text-gray-500 mt-1">{city}</p>
                  ) : null;
                })()}
            </div>
          </div>
        </div>

        {/* Date, Time & Details */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
          {/* Date & Time */}
          <div className="bg-white rounded-lg p-4">
            <div className="flex items-center gap-2 mb-2">
              <i className="fas fa-calendar-alt text-cyan-600"></i>
              <p className="text-xs text-gray-500 font-medium">Date & Time</p>
            </div>
            {(() => {
              const originalPickupDate = general.bookingtype
                ?.toLowerCase()
                .includes("arrival")
                ? arrival.arrivaldate
                : departure.pickupdate || departure.departuredate;
              const adjustedPickupDate = booking?.pickup_date_adjusted;

              return adjustedPickupDate ? (
                // Show adjusted time with indicator
                <div>
                  <div className="flex items-center gap-2">
                    <i className="fas fa-clock text-orange-600 text-sm"></i>
                    <p className="font-semibold text-orange-600">
                      {formatDateTime(adjustedPickupDate)}
                    </p>
                  </div>
                  <p className="text-xs text-gray-400 line-through mt-1">
                    {formatDateTime(originalPickupDate)}
                  </p>
                  <p className="text-xs text-orange-600 mt-0.5">
                    (เวลาปรับแล้ว)
                  </p>
                </div>
              ) : (
                // Show original time
                <p className="font-semibold text-gray-900">
                  {formatDateTime(originalPickupDate)}
                </p>
              );
            })()}
          </div>

          {/* Flight Number */}
          {(arrival.flightno || departure.flightno) && (
            <div className="bg-white rounded-lg p-4">
              <div className="flex items-center gap-2 mb-2">
                <i className="fas fa-plane text-cyan-600"></i>
                <p className="text-xs text-gray-500 font-medium">
                  Flight Number
                </p>
              </div>
              <p className="font-semibold text-gray-900">
                {arrival.flightno || departure.flightno || "-"}
              </p>
            </div>
          )}

          {/* Passengers */}
          <div className="bg-white rounded-lg p-4">
            <div className="flex items-center gap-2 mb-2">
              <i className="fas fa-users text-cyan-600"></i>
              <p className="text-xs text-gray-500 font-medium">Passengers</p>
            </div>
            <p className="font-semibold text-gray-900">
              {general.pax ||
                general.adults + general.children + general.infants ||
                0}{" "}
              pax
            </p>
            <p className="text-xs text-gray-600 mt-1">
              {general.adults || 0} Adults, {general.children || 0} Children,{" "}
              {general.infants || 0} Infants
            </p>
          </div>

          {/* Vehicle */}
          <div className="bg-white rounded-lg p-4 ">
            <div className="flex items-center gap-2 mb-2">
              <i className="fas fa-car text-cyan-600"></i>
              <p className="text-xs text-gray-500 font-medium">Vehicle</p>
            </div>
            <p className="font-semibold text-gray-900">
              {general.vehicle || "-"}
            </p>
          </div>
        </div>
      </div>

      {/* Booking Details - Simple List */}
      <Section title="Booking Details">
        <div className="space-y-3 text-sm">
          <div className="flex">
            <span className="text-gray-600 w-40">Leadname:</span>
            <span className="font-medium text-gray-900">
              {general.passengername || "-"}
            </span>
          </div>

          <div className="flex">
            <span className="text-gray-600 w-40">Mobile no:</span>
            <span className="text-gray-900">
              {general.passengertelno || "-"}
            </span>
          </div>

          {general.passengeremail && (
            <div className="flex">
              <span className="text-gray-600 w-40">Email:</span>
              <span className="text-gray-900">{general.passengeremail}</span>
            </div>
          )}

          <div className="flex">
            <span className="text-gray-600 w-40">Airport:</span>
            <span className="text-gray-900">{general.airport || "-"}</span>
          </div>

          <div className="flex">
            <span className="text-gray-600 w-40">Resort:</span>
            <span className="text-gray-900">
              {general.resort || booking?.accommodation_name || "-"}
            </span>
          </div>

          <div className="flex">
            <span className="text-gray-600 w-40">Province:</span>
            <span className="text-gray-900">
              {bookingDetail?.booking?.province || "Unknown"}
              <button
                onClick={() => setShowEditProvinceModal(true)}
                className="ml-2 text-cyan-600 hover:text-cyan-800 text-xs"
                title="Edit Province"
              >
                <i className="fas fa-edit"></i>
              </button>
            </span>
          </div>

          <div className="flex">
            <span className="text-gray-600 w-40">Vehicle details:</span>
            <span className="text-gray-900">{general.vehicle || "-"}</span>
          </div>

          <div className="flex">
            <span className="text-gray-600 w-40">No of pax travelling:</span>
            <span className="text-gray-900">
              {general.pax ||
                general.adults + general.children + general.infants ||
                0}
            </span>
          </div>

          <div className="flex">
            <span className="text-gray-600 w-40">No of adults:</span>
            <span className="text-gray-900">{general.adults || 0}</span>
          </div>

          {(general.children > 0 || general.infants > 0) && (
            <>
              <div className="flex">
                <span className="text-gray-600 w-40">Children:</span>
                <span className="text-gray-900">{general.children || 0}</span>
              </div>
              <div className="flex">
                <span className="text-gray-600 w-40">Infants:</span>
                <span className="text-gray-900">{general.infants || 0}</span>
              </div>
            </>
          )}

          <div className="flex">
            <span className="text-gray-600 w-40">Date booked:</span>
            <span className="text-gray-900">
              {formatDate(general?.bookingdate) || "-"}
            </span>
          </div>
        </div>
      </Section>

      {/* Transfer Details - Based on Type */}
      {(() => {
        const isQuote = general.bookingtype === "Quote";
        const isArrival = general.bookingtype === "Single outbound only";
        const isDeparture = general.bookingtype === "Single return only";

        if (isQuote && bookingDetail?.booking?.quote) {
          const quote = bookingDetail.booking.quote;
          return (
            <Section title="Transfer Details (Point-to-Point)">
              <div className="space-y-3 text-sm">
                <div className="flex">
                  <span className="text-gray-600 w-40">Transfer date:</span>
                  <span className="text-gray-900">
                    {formatDateTime(quote.transferdate)}
                  </span>
                </div>
                <div className="flex">
                  <span className="text-gray-600 w-40">Pickup location:</span>
                  <span className="text-gray-900">
                    {quote.pickupaddress1 || "-"}
                  </span>
                </div>
                {quote.pickupaddress2 && (
                  <div className="flex">
                    <span className="text-gray-600 w-40">Address:</span>
                    <span className="text-gray-900">
                      {[
                        quote.pickupaddress2,
                        quote.pickupaddress3,
                        quote.pickupaddress4,
                      ]
                        .filter(Boolean)
                        .join(", ")}
                    </span>
                  </div>
                )}
                <div className="flex">
                  <span className="text-gray-600 w-40">Dropoff location:</span>
                  <span className="text-gray-900">
                    {quote.dropoffaddress1 || "-"}
                  </span>
                </div>
                {quote.dropoffaddress2 && (
                  <div className="flex">
                    <span className="text-gray-600 w-40">Address:</span>
                    <span className="text-gray-900">
                      {[
                        quote.dropoffaddress2,
                        quote.dropoffaddress3,
                        quote.dropoffaddress4,
                      ]
                        .filter(Boolean)
                        .join(", ")}
                    </span>
                  </div>
                )}
              </div>
            </Section>
          );
        }

        if (isArrival) {
          const adjustedTime = booking?.pickup_date_adjusted;
          const arrivalDate = arrival?.arrivaldate;
          return (
            <Section title="Arrival Details">
              <div className="space-y-3 text-sm">
                {arrival?.fromairport && (
                  <div className="flex">
                    <span className="text-gray-600 w-40">From airport:</span>
                    <span className="text-gray-900">{arrival.fromairport}</span>
                  </div>
                )}
                {arrival?.flightno && (
                  <div className="flex">
                    <span className="text-gray-600 w-40">Flight no:</span>
                    <span className="text-gray-900">{arrival.flightno}</span>
                  </div>
                )}
                <div className="flex">
                  <span className="text-gray-600 w-40">Arrival date:</span>
                  <span className="text-gray-900">
                    {formatDate(arrivalDate)}
                  </span>
                </div>
                <div className="flex">
                  <span className="text-gray-600 w-40">Arrival time:</span>
                  <span className="text-gray-900">
                    {arrivalDate
                      ? new Date(arrivalDate).toLocaleTimeString("en-GB", {
                          hour: "2-digit",
                          minute: "2-digit",
                        })
                      : "-"}
                    {adjustedTime && (
                      <span className="ml-2 text-orange-600 text-xs">
                        (Adjusted)
                      </span>
                    )}
                  </span>
                </div>
                {arrival?.accommodationname && (
                  <>
                    <div className="flex">
                      <span className="text-gray-600 w-40">Accommodation:</span>
                      <span className="text-gray-900">
                        {arrival.accommodationname}
                      </span>
                    </div>
                    {arrival?.accommodationaddress1 && (
                      <div className="flex">
                        <span className="text-gray-600 w-40">Address:</span>
                        <span className="text-gray-900">
                          {[
                            arrival.accommodationaddress1,
                            arrival.accommodationaddress2,
                          ]
                            .filter(Boolean)
                            .join(", ")}
                        </span>
                      </div>
                    )}
                  </>
                )}
              </div>
            </Section>
          );
        }

        if (isDeparture) {
          const adjustedTime = booking?.pickup_date_adjusted;
          const departureDate = departure?.departuredate;
          const pickupDate = departure?.pickupdate;
          return (
            <Section title="Departure Details">
              <div className="space-y-3 text-sm">
                {departure?.toairport && (
                  <div className="flex">
                    <span className="text-gray-600 w-40">To airport:</span>
                    <span className="text-gray-900">{departure.toairport}</span>
                  </div>
                )}
                {departure?.flightno && (
                  <div className="flex">
                    <span className="text-gray-600 w-40">Flight no:</span>
                    <span className="text-gray-900">{departure.flightno}</span>
                  </div>
                )}
                <div className="flex">
                  <span className="text-gray-600 w-40">Departure date:</span>
                  <span className="text-gray-900">
                    {formatDate(departureDate)}
                  </span>
                </div>
                <div className="flex">
                  <span className="text-gray-600 w-40">Departure time:</span>
                  <span className="text-gray-900">
                    {departureDate
                      ? new Date(departureDate).toLocaleTimeString("en-GB", {
                          hour: "2-digit",
                          minute: "2-digit",
                        })
                      : "-"}
                  </span>
                </div>
                <div className="flex">
                  <span className="text-gray-600 w-40">Pick up date:</span>
                  <span className="text-gray-900">
                    {formatDate(pickupDate)}
                  </span>
                </div>
                <div className="flex">
                  <span className="text-gray-600 w-40">Pick up time:</span>
                  <span className="text-gray-900">
                    {pickupDate
                      ? new Date(pickupDate).toLocaleTimeString("en-GB", {
                          hour: "2-digit",
                          minute: "2-digit",
                        })
                      : "-"}
                    {adjustedTime && (
                      <span className="ml-2 text-orange-600 text-xs">
                        (Adjusted)
                      </span>
                    )}
                  </span>
                </div>
                {departure?.accommodationname && (
                  <>
                    <div className="flex">
                      <span className="text-gray-600 w-40">Accommodation:</span>
                      <span className="text-gray-900">
                        {departure.accommodationname}
                      </span>
                    </div>
                    {departure?.accommodationaddress1 && (
                      <div className="flex">
                        <span className="text-gray-600 w-40">Address:</span>
                        <span className="text-gray-900">
                          {[
                            departure.accommodationaddress1,
                            departure.accommodationaddress2,
                          ]
                            .filter(Boolean)
                            .join(", ")}
                        </span>
                      </div>
                    )}
                  </>
                )}
              </div>
            </Section>
          );
        }
      })()}

      {/* Assignment */}
      {assignment && (
        <Section title="Assignment">
          <div className="space-y-3 text-sm bg-green-50 rounded-lg p-4 border border-green-200">
            <div className="flex">
              <span className="text-gray-600 w-40">Driver:</span>
              <span className="font-medium text-gray-900">
                {assignment.driver_name || "-"}
              </span>
            </div>
            <div className="flex">
              <span className="text-gray-600 w-40">Driver phone:</span>
              <span className="text-gray-900">
                {assignment.driver_phone || "-"}
              </span>
            </div>
            <div className="flex">
              <span className="text-gray-600 w-40">Vehicle:</span>
              <span className="text-gray-900">
                {assignment.registration || "-"} ({assignment.brand}{" "}
                {assignment.model})
              </span>
            </div>
            <div className="flex">
              <span className="text-gray-600 w-40">Status:</span>
              <span
                className={`font-medium ${
                  assignment.completion_type === "NO_SHOW"
                    ? "text-red-600"
                    : "text-gray-900"
                }`}
              >
                {assignment.completion_type === "NO_SHOW"
                  ? "No Show"
                  : assignment.status === "assigned"
                  ? "Assigned"
                  : assignment.status === "in_progress"
                  ? "In Progress"
                  : assignment.status === "completed"
                  ? "Completed"
                  : assignment.status}
              </span>
            </div>
            <div className="flex">
              <span className="text-gray-600 w-40">Assigned at:</span>
              <span className="text-gray-900">
                {formatDateTime(assignment.created_at)}
              </span>
            </div>
            {assignment.assignment_notes && (
              <div className="flex">
                <span className="text-gray-600 w-40">Notes:</span>
                <span className="text-gray-900">
                  {assignment.assignment_notes}
                </span>
              </div>
            )}
          </div>
        </Section>
      )}

      {/* Notes */}
      {(apiNotes || notesLoading) && (
        <Section title="Booking Notes">
          {notesLoading ? (
            <div className="bg-gray-50 rounded-lg p-4 text-center">
              <i className="fas fa-spinner animate-spin mr-2"></i>
              Loading notes from Holiday Taxis API...
            </div>
          ) : (
            <div className="bg-gray-50 rounded-lg p-4 text-sm text-gray-800 whitespace-pre-line">
              {apiNotes || "No notes available from API"}
            </div>
          )}
        </Section>
      )}

      {/* Assignment Modal */}
      {showAssignModal && (
        <div
          className="modal-overlay"
          onClick={() => {
            setShowAssignModal(false);
            setShowDriverDropdown(false);
            setShowVehicleDropdown(false);
          }}
        >
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            {/* Modal Header */}
            <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">
                {assignment ? "Reassign Job" : "Assign Job"}
              </h3>
              <button
                onClick={() => setShowAssignModal(false)}
                className="text-gray-400 hover:text-gray-600"
              >
                <i className="fas fa-times text-xl"></i>
              </button>
            </div>

            {/* Modal Body */}
            <div className="p-6 space-y-4">
              {/* Show current assignment if exists */}
              {assignment && (
                <>
                  <div className="bg-cyan-50 border border-cyan-200 rounded-lg p-4 mb-4">
                    <p className="text-sm text-cyan-900 font-medium mb-2">
                      Current Assignment:
                    </p>
                    <p className="text-sm text-cyan-800">
                      <i className="fas fa-user mr-2"></i>
                      {assignment.driver_name}
                    </p>
                    <p className="text-sm text-cyan-800">
                      <i className="fas fa-car mr-2"></i>
                      {assignment.registration} - {assignment.brand}{" "}
                      {assignment.model}
                    </p>
                  </div>

                  {/* Warning if driver has already started */}
                  {assignment.status === "in_progress" && (
                    <div className="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4">
                      <div className="flex items-start gap-3">
                        <i className="fas fa-exclamation-triangle text-orange-600 text-lg mt-0.5"></i>
                        <div>
                          <p className="text-sm text-orange-900 font-medium">
                            คำเตือน: คนขับเริ่มงานแล้ว
                          </p>
                          <p className="text-xs text-orange-700 mt-1">
                            เมื่อเปลี่ยนคนขับหรือรถ ระบบจะแจ้ง Holiday Taxis
                            ให้ยกเลิกข้อมูลคนขับเดิมอัตโนมัติ
                          </p>
                          <p className="text-xs text-orange-700 mt-1 font-medium">
                            กรุณาแจ้งคนขับเดิมทันที เพื่อไม่ให้เกิดความสับสน
                          </p>
                        </div>
                      </div>
                    </div>
                  )}
                </>
              )}

              {/* Driver Autocomplete */}
              <div className="relative" ref={driverDropdownRef}>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Select Driver *
                </label>

                <input
                  type="text"
                  value={driverSearchTerm}
                  onChange={(e) => {
                    setDriverSearchTerm(e.target.value);
                    setShowDriverDropdown(true);
                  }}
                  onFocus={() => setShowDriverDropdown(true)}
                  placeholder="Type to search driver..."
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500"
                />

                {/* Driver Dropdown */}
                {showDriverDropdown && filteredDrivers.length > 0 && (
                  <div className="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                    {filteredDrivers.map((driver) => (
                      <div
                        key={driver.id}
                        onClick={() => handleSelectDriver(driver)}
                        className="px-4 py-2 hover:bg-cyan-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                      >
                        <div className="font-medium text-gray-900">
                          {driver.name || "N/A"}
                        </div>
                        <div className="text-sm text-gray-600">
                          {driver.phone_number || "N/A"}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Vehicle Autocomplete */}
              <div className="relative" ref={vehicleDropdownRef}>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Select Vehicle *
                </label>

                <input
                  type="text"
                  value={vehicleSearchTerm}
                  onChange={(e) => {
                    setVehicleSearchTerm(e.target.value);
                    setShowVehicleDropdown(true);
                  }}
                  onFocus={() => setShowVehicleDropdown(true)}
                  placeholder="Type to search vehicle..."
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500"
                />

                {/* Vehicle Dropdown */}
                {showVehicleDropdown && filteredVehicles.length > 0 && (
                  <div className="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                    {filteredVehicles.map((vehicle) => (
                      <div
                        key={vehicle.id}
                        onClick={() => handleSelectVehicle(vehicle)}
                        className="px-4 py-2 hover:bg-cyan-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                      >
                        <div className="font-medium text-gray-900">
                          {vehicle.registration || "N/A"}
                        </div>
                        <div className="text-sm text-gray-600">
                          {vehicle.brand || "N/A"} {vehicle.model || ""}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Notes */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Notes (Optional)
                </label>
                <textarea
                  value={assignmentForm.notes}
                  onChange={(e) =>
                    setAssignmentForm({
                      ...assignmentForm,
                      notes: e.target.value,
                    })
                  }
                  rows="3"
                  placeholder="Any special instructions for the driver..."
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500"
                />
              </div>
            </div>

            {/* Modal Footer */}
            <div className="flex items-center justify-between px-6 py-4 border-t border-gray-200 bg-gray-50">
              {assignment && (
                <button
                  onClick={handleUnassign}
                  className="px-4 py-2 text-sm font-medium text-red-700 bg-white border border-red-300 rounded-lg hover:bg-red-50"
                >
                  <i className="fas fa-times-circle mr-2"></i>
                  Unassign
                </button>
              )}

              <div className="flex gap-3 ml-auto">
                <button
                  onClick={() => setShowAssignModal(false)}
                  className="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                  Cancel
                </button>
                <button
                  onClick={assignment ? handleReassign : handleAssignJob}
                  disabled={assignLoading}
                  className={`px-4 py-2 text-sm font-medium rounded-lg text-white transition-colors ${getCompanyClass(
                    "primary"
                  )} ${getCompanyClass("primaryHover")} disabled:opacity-50`}
                >
                  {assignLoading ? (
                    <>
                      <i className="fas fa-spinner animate-spin mr-2"></i>
                      {assignment ? "Reassigning..." : "Assigning..."}
                    </>
                  ) : (
                    <>
                      <i className="fas fa-check mr-2"></i>
                      {assignment ? "Reassign Job" : "Assign Job"}
                    </>
                  )}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Tracking Link Modal */}
      {showTrackingModal && trackingLink && (
        <div
          className="modal-overlay"
          onClick={() => {
            setShowTrackingModal(false);
            setMessageCopied(false); // Reset copy state when closing modal
          }}
        >
          <div className="modal-content" onClick={(e) => e.stopPropagation()}>
            {/* Modal Header */}
            <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
              <h3 className="text-lg font-semibold text-gray-900">
                <i className="fas fa-link text-purple-600 mr-2"></i>
                Driver Tracking Link
              </h3>
              <button
                onClick={() => {
                  setShowTrackingModal(false);
                  setMessageCopied(false); // Reset copy state when closing modal
                }}
                className="text-gray-400 hover:text-gray-600"
              >
                <i className="fas fa-times text-xl"></i>
              </button>
            </div>

            {/* Modal Body */}
            <div className="p-6 space-y-4">
              <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                <div className="flex items-start gap-3">
                  <i className="fas fa-check-circle text-green-600 text-xl mt-0.5"></i>
                  <div>
                    <p className="font-medium text-green-900">
                      Tracking Link Generated!
                    </p>
                    <p className="text-sm text-green-700 mt-1">
                      Send this link to the driver via LINE or WhatsApp
                    </p>
                  </div>
                </div>
              </div>

              {/* Link Display */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Tracking URL
                </label>
                <div className="flex gap-2">
                  <input
                    type="text"
                    value={trackingLink.tracking_url}
                    readOnly
                    className="flex-1 px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-sm font-mono"
                  />
                  <button
                    onClick={handleCopyLink}
                    className="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 flex items-center gap-2"
                  >
                    <i className="fas fa-copy"></i>
                    Copy
                  </button>
                </div>
              </div>

              {/* Message for Driver */}
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
                    {messageCopied ? "คัดลอกข้อความแล้ว" : "คัดลอกข้อความ"}
                  </button>
                </div>

                <div className="bg-white rounded-lg p-4 text-sm text-gray-800 whitespace-pre-wrap break-words border border-cyan-200">
                  {generateDriverMessage()}
                </div>

                <p className="text-xs text-cyan-700 mt-3">
                  <i className="fas fa-info-circle mr-1"></i>
                  คัดลอกข้อความนี้และส่งให้คนขับผ่าน LINE หรือ WhatsApp
                </p>
              </div>
            </div>

            {/* Modal Footer */}
            <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
              <button
                onClick={() => {
                  setShowTrackingModal(false);
                  setMessageCopied(false); // Reset copy state when closing modal
                }}
                className="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-white"
              >
                Close
              </button>
              <button
                onClick={handleCopyLink}
                className="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700"
              >
                <i className="fas fa-copy mr-2"></i>
                Copy Link
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Edit Province Modal */}
      {showEditProvinceModal && bookingDetail && (
        <EditProvinceModal
          booking={{
            ref: ref,
            province: bookingDetail.booking.province,
            province_source: bookingDetail.booking.province_source,
          }}
          provinces={provinces}
          onClose={() => setShowEditProvinceModal(false)}
          onSave={handleProvinceSaved}
        />
      )}
    </div>
  );
}

export default BookingDetailPage;
