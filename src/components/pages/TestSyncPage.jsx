import { useState } from "react";
import * as XLSX from "xlsx";
import { COMPANY } from "../../config/company";
import { backendApi } from "../../services/backendApi";

export default function TestSyncPage() {
  const [dateFrom, setDateFrom] = useState("2025-06-01T00:00:00");
  const [dateTo, setDateTo] = useState("2025-06-30T23:59:59");
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState(null);

  // Manual Sync states
  const today = new Date().toISOString().split("T")[0];
  const [manualDateFrom, setManualDateFrom] = useState(`${today}T00:00:00`);
  const [manualDateTo, setManualDateTo] = useState(`${today}T23:59:59`);
  const [manualLoading, setManualLoading] = useState(false);
  const [manualResult, setManualResult] = useState(null);
  const [manualError, setManualError] = useState(null);

  // Check booking states
  const [bookingRef, setBookingRef] = useState("");
  const [checkLoading, setCheckLoading] = useState(false);
  const [bookingData, setBookingData] = useState(null);
  const [checkError, setCheckError] = useState(null);

  // Fetch single booking from Production API
  const [prodBookingRef, setProdBookingRef] = useState("");
  const [prodLoading, setProdLoading] = useState(false);
  const [prodBookingData, setProdBookingData] = useState(null);
  const [prodError, setProdError] = useState(null);

  // Fetch single booking from Test API
  const [testBookingRef, setTestBookingRef] = useState("");
  const [testLoading, setTestLoading] = useState(false);
  const [testBookingData, setTestBookingData] = useState(null);
  const [testError, setTestError] = useState(null);

  // Force-close stale tracking
  const [staleMinHours, setStaleMinHours] = useState(4);
  const [staleLoading, setStaleLoading] = useState(false);
  const [staleResult, setStaleResult] = useState(null);
  const [staleError, setStaleError] = useState(null);

  const handlePreviewStale = async () => {
    setStaleLoading(true);
    setStaleError(null);
    setStaleResult(null);
    try {
      const res = await fetch(
        `/api/dev/force-close-stale-tracking.php?min_hours=${staleMinHours}`,
        { method: "GET" }
      );
      const data = await res.json();
      if (!data.success) throw new Error(data.error || "Preview failed");
      setStaleResult(data.data);
    } catch (err) {
      setStaleError(err.message);
    } finally {
      setStaleLoading(false);
    }
  };

  const handleExportStale = () => {
    if (!staleResult || staleResult.candidates_count === 0) {
      alert("ไม่มีข้อมูลให้ export - กด Preview ก่อน");
      return;
    }

    const rows = staleResult.candidates.map((c) => ({
      "Booking Ref": c.booking_ref,
      "Started At": c.started_at,
      Duration: c.duration_text,
      "Minutes Tracking": c.minutes_tracking,
      "Pickup Date": c.pickup_date || "",
      "Pickup Location": c.pickup_location || "",
      "Dropoff Location": c.dropoff_location || "",
      "Driver Name": c.driver_name || "",
      "Driver Phone": c.driver_phone || "",
      Vehicle: c.vehicle || "",
      "Passenger Name": c.passenger_name || "",
      "Passenger Phone": c.passenger_phone || "",
    }));

    const ws = XLSX.utils.json_to_sheet(rows);
    ws["!cols"] = [
      { wch: 18 }, { wch: 20 }, { wch: 14 }, { wch: 10 },
      { wch: 20 }, { wch: 30 }, { wch: 30 },
      { wch: 22 }, { wch: 16 }, { wch: 26 },
      { wch: 22 }, { wch: 16 },
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Stale Tracking");

    const ts = new Date().toISOString().replace(/[:.]/g, "-").slice(0, 19);
    XLSX.writeFile(wb, `stale-tracking-${ts}.xlsx`);
  };

  const handleForceCloseStale = async () => {
    const count = staleResult?.candidates_count ?? 0;
    if (count === 0) {
      alert("ไม่มี tracking ที่ค้างให้ปิด - กด Preview ก่อน");
      return;
    }
    if (
      !confirm(
        `ยืนยันปิด tracking ที่ค้าง ${count} รายการ?\n\n` +
          `- เปลี่ยน driver_tracking_tokens.status = 'completed'\n` +
          `- completion_type = 'FORCE_CLOSED'\n` +
          `- update driver_vehicle_assignments + bookings ตามไปด้วย\n` +
          `- ไม่ส่งข้อมูลไป Holiday Taxis (เป็นการ cleanup local DB เท่านั้น)`
      )
    ) {
      return;
    }
    setStaleLoading(true);
    setStaleError(null);
    try {
      const res = await fetch(
        `/api/dev/force-close-stale-tracking.php?min_hours=${staleMinHours}`,
        { method: "POST" }
      );
      const data = await res.json();
      if (!data.success) throw new Error(data.error || "Force close failed");
      setStaleResult(data.data);
      alert(
        `ปิดเรียบร้อย: tokens ${data.data.affected.tokens}, ` +
          `assignments ${data.data.affected.assignments}, ` +
          `bookings ${data.data.affected.bookings}`
      );
    } catch (err) {
      setStaleError(err.message);
    } finally {
      setStaleLoading(false);
    }
  };

  const handleSync = async () => {
    setLoading(true);
    setError(null);
    setResult(null);

    try {
      const response = await fetch("/api/dev/test-sync-arrivals.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          dateFrom,
          dateTo,
        }),
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.error || "Sync failed");
      }

      setResult(data.data);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const handleManualSync = async () => {
    setManualLoading(true);
    setManualError(null);
    setManualResult(null);

    try {
      const response = await backendApi.manualSyncArrivals(
        manualDateFrom,
        manualDateTo
      );

      if (!response.success) {
        throw new Error(response.error || "Manual sync failed");
      }

      setManualResult(response.data);
    } catch (err) {
      setManualError(err.message);
    } finally {
      setManualLoading(false);
    }
  };

  const handleCheckBooking = async () => {
    if (!bookingRef.trim()) {
      setCheckError("Please enter booking reference");
      return;
    }

    setCheckLoading(true);
    setCheckError(null);
    setBookingData(null);

    try {
      const response = await fetch("/api/dev/check-booking.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          bookingRef: bookingRef.trim(),
        }),
      });

      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.error || "Check failed");
      }

      setBookingData(data.data);
    } catch (err) {
      setCheckError(err.message);
    } finally {
      setCheckLoading(false);
    }
  };

  const handleFetchProductionBooking = async () => {
    if (!prodBookingRef.trim()) {
      setProdError("Please enter booking reference");
      return;
    }

    setProdLoading(true);
    setProdError(null);
    setProdBookingData(null);

    try {
      const response = await backendApi.holidayTaxis.getBookingByRef(
        prodBookingRef.trim()
      );

      if (!response.success) {
        throw new Error(response.error || "Failed to fetch booking");
      }

      setProdBookingData(response.data);
    } catch (err) {
      setProdError(err.message);
    } finally {
      setProdLoading(false);
    }
  };

  const handleFetchTestBooking = async () => {
    if (!testBookingRef.trim()) {
      setTestError("Please enter booking reference");
      return;
    }

    setTestLoading(true);
    setTestError(null);
    setTestBookingData(null);

    try {
      const response = await backendApi.holidayTaxis.getBookingByRefTest(
        testBookingRef.trim()
      );

      if (!response.success) {
        throw new Error(response.error || "Failed to fetch booking from Test API");
      }

      setTestBookingData(response.data);
    } catch (err) {
      setTestError(err.message);
    } finally {
      setTestLoading(false);
    }
  };

  return (
    <div className="p-6">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-800 mb-2">
          Sync Management
        </h1>
        <p className="text-sm text-gray-600">
          ทดสอบและจัดการการซิงค์ข้อมูล Booking จาก Holiday Taxis
        </p>
      </div>

      {/* Force-Close Stale Tracking */}
      <div className="bg-white rounded-lg shadow p-6 max-w-2xl mb-6 border-2 border-orange-300">
        <h2 className="text-lg font-semibold text-gray-800 mb-2 flex items-center">
          <span className="bg-orange-100 text-orange-800 text-xs font-semibold mr-2 px-2.5 py-0.5 rounded">
            CLEANUP
          </span>
          🧹 ปิด Tracking ที่ค้าง (Force Close Stale)
        </h2>
        <p className="text-sm text-gray-600 mb-4">
          ปิด <code>driver_tracking_tokens</code> ที่ status=&apos;active&apos;
          มานานเกิน N ชม. โดยที่ driver ลืมกดเสร็จงาน
          <br />
          <span className="text-xs text-orange-700">
            * เป็นการ cleanup local DB เท่านั้น ไม่ส่งข้อมูลไป Holiday Taxis
          </span>
        </p>

        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              ปิด tracking ที่เปิดมานานเกิน (ชม.)
            </label>
            <input
              type="number"
              min="1"
              value={staleMinHours}
              onChange={(e) => setStaleMinHours(parseInt(e.target.value) || 1)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
            />
          </div>

          <div className="flex gap-2 flex-wrap">
            <button
              onClick={handlePreviewStale}
              disabled={staleLoading}
              className={`flex-1 min-w-[140px] bg-gray-600 text-white px-4 py-2 rounded-md font-medium ${
                staleLoading
                  ? "opacity-50 cursor-not-allowed"
                  : "hover:bg-gray-700"
              }`}
            >
              {staleLoading ? "Loading..." : "🔍 Preview (Dry Run)"}
            </button>
            <button
              onClick={handleExportStale}
              disabled={
                !staleResult || staleResult.candidates_count === 0
              }
              className={`flex-1 min-w-[140px] bg-green-600 text-white px-4 py-2 rounded-md font-medium ${
                !staleResult || staleResult.candidates_count === 0
                  ? "opacity-50 cursor-not-allowed"
                  : "hover:bg-green-700"
              }`}
            >
              📊 Export Excel
              {staleResult?.candidates_count
                ? ` (${staleResult.candidates_count})`
                : ""}
            </button>
            <button
              onClick={handleForceCloseStale}
              disabled={
                staleLoading || !staleResult || staleResult.dry_run === false
              }
              className={`flex-1 min-w-[140px] bg-orange-600 text-white px-4 py-2 rounded-md font-medium ${
                staleLoading || !staleResult || staleResult.dry_run === false
                  ? "opacity-50 cursor-not-allowed"
                  : "hover:bg-orange-700"
              }`}
            >
              {staleLoading
                ? "Closing..."
                : `🧹 Force Close ${
                    staleResult?.dry_run ? `(${staleResult.candidates_count})` : ""
                  }`}
            </button>
          </div>
          <p className="text-xs text-gray-500">
            แนะนำ: Preview → Export Excel เก็บไว้ → Force Close
          </p>
        </div>

        {staleError && (
          <div className="mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
            <strong>Error:</strong> {staleError}
          </div>
        )}

        {staleResult && (
          <div className="mt-4 p-4 bg-orange-50 border border-orange-200 rounded max-h-96 overflow-y-auto">
            <div className="mb-3 text-sm">
              <span
                className={`inline-block px-2 py-0.5 rounded text-xs font-bold mr-2 ${
                  staleResult.dry_run
                    ? "bg-gray-200 text-gray-800"
                    : "bg-green-200 text-green-800"
                }`}
              >
                {staleResult.dry_run ? "DRY RUN" : "EXECUTED"}
              </span>
              <strong>เจอ {staleResult.candidates_count} รายการ</strong>
              {!staleResult.dry_run && (
                <span className="ml-2 text-gray-700">
                  → ปิด tokens {staleResult.affected.tokens}, assignments{" "}
                  {staleResult.affected.assignments}, bookings{" "}
                  {staleResult.affected.bookings}
                </span>
              )}
            </div>

            {staleResult.candidates_count > 0 && (
              <table className="w-full text-xs">
                <thead className="bg-orange-100">
                  <tr>
                    <th className="text-left py-2 px-2">Booking</th>
                    <th className="text-left py-2 px-2">Started At</th>
                    <th className="text-left py-2 px-2">Duration</th>
                  </tr>
                </thead>
                <tbody>
                  {staleResult.candidates.map((c) => (
                    <tr
                      key={c.token_id}
                      className="border-b border-orange-100"
                    >
                      <td className="py-1 px-2 font-mono">{c.booking_ref}</td>
                      <td className="py-1 px-2">{c.started_at}</td>
                      <td className="py-1 px-2 text-orange-700 font-semibold">
                        {c.duration_text}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        )}
      </div>

      {/* Fetch Single Booking from Test API */}
      <div className="bg-white rounded-lg shadow p-6 max-w-2xl mb-6">
        <h2 className="text-lg font-semibold text-gray-800 mb-4 flex items-center">
          <span className="bg-yellow-100 text-yellow-800 text-xs font-semibold mr-2 px-2.5 py-0.5 rounded">
            TEST API
          </span>
          Fetch Single Booking (GET /bookings/{"{bookingRef}"})
        </h2>
        <p className="text-sm text-gray-600 mb-4">
          ดึงข้อมูล Booking เดี่ยวจาก Test API โดยใช้ Booking Reference
        </p>

        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Booking Reference
            </label>
            <input
              type="text"
              value={testBookingRef}
              onChange={(e) => setTestBookingRef(e.target.value)}
              onKeyPress={(e) =>
                e.key === "Enter" && handleFetchTestBooking()
              }
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500"
              placeholder="e.g., TCS-25581676"
            />
          </div>

          <button
            onClick={handleFetchTestBooking}
            disabled={testLoading}
            className={`w-full bg-yellow-600 text-white px-4 py-2 rounded-md font-medium ${
              testLoading
                ? "opacity-50 cursor-not-allowed"
                : "hover:bg-yellow-700"
            }`}
          >
            {testLoading
              ? "Fetching..."
              : "🔍 Fetch Booking from Test API"}
          </button>
        </div>

        {testError && (
          <div className="mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
            <p className="font-medium">Error:</p>
            <p>{testError}</p>
          </div>
        )}

        {testBookingData && (
          <div className="mt-4 p-4 bg-green-50 border border-green-200 rounded max-h-96 overflow-y-auto">
            <div className="flex items-center justify-between mb-3">
              <div>
                <p className="font-semibold text-green-900">
                  ✅ Booking{" "}
                  {testBookingData.action === "created" ? "Created" : "Updated"}{" "}
                  Successfully!
                </p>
                <p className="text-xs text-green-700 mt-1">
                  Booking has been saved to database from Test API
                </p>
              </div>
              <button
                onClick={() => {
                  const fullData = testBookingData.full_api_response || testBookingData.booking_data;
                  const formatted = JSON.stringify(fullData, null, 2);
                  navigator.clipboard.writeText(formatted);
                  alert("Full API response copied to clipboard!");
                }}
                className="text-xs bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded"
              >
                📋 Copy Full JSON
              </button>
            </div>

            {/* Summary Card */}
            <div className="bg-white rounded-lg p-3 mb-3 border border-green-300">
              <div className="grid grid-cols-2 gap-2 text-sm">
                <span className="font-medium text-gray-700">Booking Ref:</span>
                <span className="text-gray-900 font-semibold">
                  {testBookingData.booking_ref}
                </span>

                <span className="font-medium text-gray-700">Action:</span>
                <span
                  className={`font-semibold ${
                    testBookingData.action === "created"
                      ? "text-green-700"
                      : "text-cyan-700"
                  }`}
                >
                  {testBookingData.action === "created"
                    ? "✨ New Booking"
                    : "🔄 Updated"}
                </span>

                <span className="font-medium text-gray-700">Status:</span>
                <span className="text-gray-900">{testBookingData.status}</span>

                <span className="font-medium text-gray-700">Passenger:</span>
                <span className="text-gray-900">
                  {testBookingData.passenger || "-"}
                </span>

                <span className="font-medium text-gray-700">Pickup Date:</span>
                <span className="text-gray-900">
                  {testBookingData.pickup_date || "-"}
                </span>

                {testBookingData.province && (
                  <>
                    <span className="font-medium text-gray-700">Province:</span>
                    <span className="text-gray-900">
                      {testBookingData.province}
                    </span>
                  </>
                )}
              </div>
            </div>
            {/* Detailed Booking Data */}
            <div className="space-y-2 text-sm">
              {/* General Info */}
              {testBookingData.booking_data?.general && (
                <div className="mb-3">
                  <p className="font-semibold text-green-900 mb-2">
                    General Information:
                  </p>
                  <div className="grid grid-cols-2 gap-2 bg-white p-3 rounded">
                    <span className="font-medium text-gray-700">
                      Booking Ref:
                    </span>
                    <span className="text-gray-900">
                      {testBookingData.booking_data.general.bookingreference ||
                        "-"}
                    </span>

                    <span className="font-medium text-gray-700">Status:</span>
                    <span className="text-gray-900">
                      {testBookingData.booking_data.general.status || "-"}
                    </span>

                    <span className="font-medium text-gray-700">
                      Booking Type:
                    </span>
                    <span className="text-gray-900">
                      {testBookingData.booking_data.general.bookingtype || "-"}
                    </span>

                    <span className="font-medium text-gray-700">
                      Passenger:
                    </span>
                    <span className="text-gray-900">
                      {testBookingData.booking_data.general.passengername ||
                        "-"}
                    </span>

                    <span className="font-medium text-gray-700">Email:</span>
                    <span className="text-gray-900 text-xs break-all">
                      {testBookingData.booking_data.general.passengeremail ||
                        "-"}
                    </span>

                    <span className="font-medium text-gray-700">Phone:</span>
                    <span className="text-gray-900">
                      {testBookingData.booking_data.general.passengertelno ||
                        "-"}
                    </span>

                    <span className="font-medium text-gray-700">Vehicle:</span>
                    <span className="text-gray-900">
                      {testBookingData.booking_data.general.vehicle || "-"}
                    </span>

                    <span className="font-medium text-gray-700">
                      Passengers:
                    </span>
                    <span className="text-gray-900">
                      A: {testBookingData.booking_data.general.adults || 0}, C:{" "}
                      {testBookingData.booking_data.general.children || 0}, I:{" "}
                      {testBookingData.booking_data.general.infants || 0}
                    </span>
                  </div>
                </div>
              )}

              {/* Arrival Info */}
              {testBookingData.booking_data?.arrival &&
                Object.keys(testBookingData.booking_data.arrival).length >
                  0 && (
                  <div className="mb-3">
                    <p className="font-semibold text-green-900 mb-2">
                      Arrival Information:
                    </p>
                    <div className="grid grid-cols-2 gap-2 bg-white p-3 rounded">
                      <span className="font-medium text-gray-700">
                        Arrival Date:
                      </span>
                      <span className="text-gray-900">
                        {testBookingData.booking_data.arrival.arrivaldate ||
                          "-"}
                      </span>

                      <span className="font-medium text-gray-700">
                        Flight No:
                      </span>
                      <span className="text-gray-900">
                        {testBookingData.booking_data.arrival.flightno || "-"}
                      </span>

                      <span className="font-medium text-gray-700">
                        From Airport:
                      </span>
                      <span className="text-gray-900">
                        {testBookingData.booking_data.arrival.fromairport ||
                          "-"}
                      </span>

                      <span className="font-medium text-gray-700">
                        Accommodation:
                      </span>
                      <span className="text-gray-900">
                        {testBookingData.booking_data.arrival
                          .accommodationname || "-"}
                      </span>

                      <span className="font-medium text-gray-700">
                        Address:
                      </span>
                      <span className="text-gray-900 text-xs">
                        {[
                          testBookingData.booking_data.arrival
                            .accommodationaddress1,
                          testBookingData.booking_data.arrival
                            .accommodationaddress2,
                        ]
                          .filter(Boolean)
                          .join(", ") || "-"}
                      </span>
                    </div>
                  </div>
                )}

              {/* Departure Info */}
              {testBookingData.booking_data?.departure &&
                Object.keys(testBookingData.booking_data.departure).length >
                  0 && (
                  <div className="mb-3">
                    <p className="font-semibold text-green-900 mb-2">
                      Departure Information:
                    </p>
                    <div className="grid grid-cols-2 gap-2 bg-white p-3 rounded">
                      <span className="font-medium text-gray-700">
                        Departure Date:
                      </span>
                      <span className="text-gray-900">
                        {testBookingData.booking_data.departure.departuredate ||
                          "-"}
                      </span>

                      <span className="font-medium text-gray-700">
                        Pickup Date:
                      </span>
                      <span className="text-gray-900">
                        {testBookingData.booking_data.departure.pickupdate ||
                          "-"}
                      </span>

                      <span className="font-medium text-gray-700">
                        Flight No:
                      </span>
                      <span className="text-gray-900">
                        {testBookingData.booking_data.departure.flightno || "-"}
                      </span>

                      <span className="font-medium text-gray-700">
                        To Airport:
                      </span>
                      <span className="text-gray-900">
                        {testBookingData.booking_data.departure.toairport ||
                          "-"}
                      </span>

                      <span className="font-medium text-gray-700">
                        Accommodation:
                      </span>
                      <span className="text-gray-900">
                        {testBookingData.booking_data.departure
                          .accommodationname || "-"}
                      </span>
                    </div>
                  </div>
                )}
            </div>

            {/* Full JSON Data Section */}
            <div className="mt-4 bg-gray-900 rounded-lg p-4 border border-gray-700">
              <div className="flex items-center justify-between mb-2">
                <p className="font-semibold text-gray-100 text-sm">
                  📄 Complete API Response (All Fields)
                </p>
                <button
                  onClick={() => {
                    const fullData = testBookingData.full_api_response || testBookingData.booking_data;
                    const formatted = JSON.stringify(fullData, null, 2);
                    navigator.clipboard.writeText(formatted);
                    alert("Complete JSON copied!");
                  }}
                  className="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded"
                >
                  📋 Copy All
                </button>
              </div>
              <pre className="text-xs text-green-400 overflow-x-auto max-h-96 overflow-y-auto bg-gray-950 p-3 rounded">
                {JSON.stringify(
                  testBookingData.full_api_response || testBookingData.booking_data,
                  null,
                  2
                )}
              </pre>
            </div>
          </div>
        )}
      </div>

      {/* Fetch Single Booking from Production API */}
      <div className="bg-white rounded-lg shadow p-6 max-w-2xl mb-6">
        <h2 className="text-lg font-semibold text-gray-800 mb-4 flex items-center">
          <span className="bg-cyan-100 text-cyan-800 text-xs font-semibold mr-2 px-2.5 py-0.5 rounded">
            PRODUCTION
          </span>
          Fetch Single Booking (GET /bookings/{"{bookingRef}"})
        </h2>
        <p className="text-sm text-gray-600 mb-4">
          ดึงข้อมูล Booking เดี่ยวจาก Production API โดยใช้ Booking Reference
        </p>

        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Booking Reference
            </label>
            <input
              type="text"
              value={prodBookingRef}
              onChange={(e) => setProdBookingRef(e.target.value)}
              onKeyPress={(e) =>
                e.key === "Enter" && handleFetchProductionBooking()
              }
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-cyan-500"
              placeholder="e.g., TCS-25581676"
            />
          </div>

          <button
            onClick={handleFetchProductionBooking}
            disabled={prodLoading}
            className={`w-full bg-cyan-600 text-white px-4 py-2 rounded-md font-medium ${
              prodLoading
                ? "opacity-50 cursor-not-allowed"
                : "hover:bg-cyan-700"
            }`}
          >
            {prodLoading
              ? "Fetching..."
              : "🔍 Fetch Booking from Production API"}
          </button>
        </div>

        {prodError && (
          <div className="mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
            <p className="font-medium">Error:</p>
            <p>{prodError}</p>
          </div>
        )}

        {prodBookingData && (
          <div className="mt-4 p-4 bg-green-50 border border-green-200 rounded max-h-96 overflow-y-auto">
            <div className="flex items-center justify-between mb-3">
              <div>
                <p className="font-semibold text-green-900">
                  ✅ Booking{" "}
                  {prodBookingData.action === "created" ? "Created" : "Updated"}{" "}
                  Successfully!
                </p>
                <p className="text-xs text-green-700 mt-1">
                  Booking has been saved to database
                </p>
              </div>
              <button
                onClick={() => {
                  const fullData = prodBookingData.full_api_response || prodBookingData.booking_data;
                  const formatted = JSON.stringify(fullData, null, 2);
                  navigator.clipboard.writeText(formatted);
                  alert("Full API response copied to clipboard!");
                }}
                className="text-xs bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded"
              >
                📋 Copy Full JSON
              </button>
            </div>

            {/* Summary Card */}
            <div className="bg-white rounded-lg p-3 mb-3 border border-green-300">
              <div className="grid grid-cols-2 gap-2 text-sm">
                <span className="font-medium text-gray-700">Booking Ref:</span>
                <span className="text-gray-900 font-semibold">
                  {prodBookingData.booking_ref}
                </span>

                <span className="font-medium text-gray-700">Action:</span>
                <span
                  className={`font-semibold ${
                    prodBookingData.action === "created"
                      ? "text-green-700"
                      : "text-cyan-700"
                  }`}
                >
                  {prodBookingData.action === "created"
                    ? "✨ New Booking"
                    : "🔄 Updated"}
                </span>

                <span className="font-medium text-gray-700">Status:</span>
                <span className="text-gray-900">{prodBookingData.status}</span>

                <span className="font-medium text-gray-700">Passenger:</span>
                <span className="text-gray-900">
                  {prodBookingData.passenger || "-"}
                </span>

                <span className="font-medium text-gray-700">Pickup Date:</span>
                <span className="text-gray-900">
                  {prodBookingData.pickup_date || "-"}
                </span>

                {prodBookingData.province && (
                  <>
                    <span className="font-medium text-gray-700">Province:</span>
                    <span className="text-gray-900">
                      {prodBookingData.province}
                    </span>
                  </>
                )}
              </div>
            </div>
            {/* Detailed Booking Data */}
            <div className="space-y-2 text-sm">
              {/* General Info */}
              {prodBookingData.booking_data?.general && (
                <div className="mb-3">
                  <p className="font-semibold text-green-900 mb-2">
                    General Information:
                  </p>
                  <div className="grid grid-cols-2 gap-2 bg-white p-3 rounded">
                    <span className="font-medium text-gray-700">
                      Booking Ref:
                    </span>
                    <span className="text-gray-900">
                      {prodBookingData.booking_data.general.bookingreference ||
                        "-"}
                    </span>

                    <span className="font-medium text-gray-700">Status:</span>
                    <span className="text-gray-900">
                      {prodBookingData.booking_data.general.status || "-"}
                    </span>

                    <span className="font-medium text-gray-700">
                      Booking Type:
                    </span>
                    <span className="text-gray-900">
                      {prodBookingData.booking_data.general.bookingtype || "-"}
                    </span>

                    <span className="font-medium text-gray-700">
                      Passenger:
                    </span>
                    <span className="text-gray-900">
                      {prodBookingData.booking_data.general.passengername ||
                        "-"}
                    </span>

                    <span className="font-medium text-gray-700">Email:</span>
                    <span className="text-gray-900 text-xs break-all">
                      {prodBookingData.booking_data.general.passengeremail ||
                        "-"}
                    </span>

                    <span className="font-medium text-gray-700">Phone:</span>
                    <span className="text-gray-900">
                      {prodBookingData.booking_data.general.passengertelno ||
                        "-"}
                    </span>

                    <span className="font-medium text-gray-700">Vehicle:</span>
                    <span className="text-gray-900">
                      {prodBookingData.booking_data.general.vehicle || "-"}
                    </span>

                    <span className="font-medium text-gray-700">
                      Passengers:
                    </span>
                    <span className="text-gray-900">
                      A: {prodBookingData.booking_data.general.adults || 0}, C:{" "}
                      {prodBookingData.booking_data.general.children || 0}, I:{" "}
                      {prodBookingData.booking_data.general.infants || 0}
                    </span>
                  </div>
                </div>
              )}

              {/* Arrival Info */}
              {prodBookingData.booking_data?.arrival &&
                Object.keys(prodBookingData.booking_data.arrival).length >
                  0 && (
                  <div className="mb-3">
                    <p className="font-semibold text-green-900 mb-2">
                      Arrival Information:
                    </p>
                    <div className="grid grid-cols-2 gap-2 bg-white p-3 rounded">
                      <span className="font-medium text-gray-700">
                        Arrival Date:
                      </span>
                      <span className="text-gray-900">
                        {prodBookingData.booking_data.arrival.arrivaldate ||
                          "-"}
                      </span>

                      <span className="font-medium text-gray-700">
                        Flight No:
                      </span>
                      <span className="text-gray-900">
                        {prodBookingData.booking_data.arrival.flightno || "-"}
                      </span>

                      <span className="font-medium text-gray-700">
                        From Airport:
                      </span>
                      <span className="text-gray-900">
                        {prodBookingData.booking_data.arrival.fromairport ||
                          "-"}
                      </span>

                      <span className="font-medium text-gray-700">
                        Accommodation:
                      </span>
                      <span className="text-gray-900">
                        {prodBookingData.booking_data.arrival
                          .accommodationname || "-"}
                      </span>

                      <span className="font-medium text-gray-700">
                        Address:
                      </span>
                      <span className="text-gray-900 text-xs">
                        {[
                          prodBookingData.booking_data.arrival
                            .accommodationaddress1,
                          prodBookingData.booking_data.arrival
                            .accommodationaddress2,
                        ]
                          .filter(Boolean)
                          .join(", ") || "-"}
                      </span>
                    </div>
                  </div>
                )}

              {/* Departure Info */}
              {prodBookingData.booking_data?.departure &&
                Object.keys(prodBookingData.booking_data.departure).length >
                  0 && (
                  <div className="mb-3">
                    <p className="font-semibold text-green-900 mb-2">
                      Departure Information:
                    </p>
                    <div className="grid grid-cols-2 gap-2 bg-white p-3 rounded">
                      <span className="font-medium text-gray-700">
                        Departure Date:
                      </span>
                      <span className="text-gray-900">
                        {prodBookingData.booking_data.departure.departuredate ||
                          "-"}
                      </span>

                      <span className="font-medium text-gray-700">
                        Pickup Date:
                      </span>
                      <span className="text-gray-900">
                        {prodBookingData.booking_data.departure.pickupdate ||
                          "-"}
                      </span>

                      <span className="font-medium text-gray-700">
                        Flight No:
                      </span>
                      <span className="text-gray-900">
                        {prodBookingData.booking_data.departure.flightno || "-"}
                      </span>

                      <span className="font-medium text-gray-700">
                        To Airport:
                      </span>
                      <span className="text-gray-900">
                        {prodBookingData.booking_data.departure.toairport ||
                          "-"}
                      </span>

                      <span className="font-medium text-gray-700">
                        Accommodation:
                      </span>
                      <span className="text-gray-900">
                        {prodBookingData.booking_data.departure
                          .accommodationname || "-"}
                      </span>
                    </div>
                  </div>
                )}
            </div>

            {/* Full JSON Data Section */}
            <div className="mt-4 bg-gray-900 rounded-lg p-4 border border-gray-700">
              <div className="flex items-center justify-between mb-2">
                <p className="font-semibold text-gray-100 text-sm">
                  📄 Complete API Response (All Fields)
                </p>
                <button
                  onClick={() => {
                    const fullData = prodBookingData.full_api_response || prodBookingData.booking_data;
                    const formatted = JSON.stringify(fullData, null, 2);
                    navigator.clipboard.writeText(formatted);
                    alert("Complete JSON copied!");
                  }}
                  className="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded"
                >
                  📋 Copy All
                </button>
              </div>
              <pre className="text-xs text-green-400 overflow-x-auto max-h-96 overflow-y-auto bg-gray-950 p-3 rounded">
                {JSON.stringify(
                  prodBookingData.full_api_response || prodBookingData.booking_data,
                  null,
                  2
                )}
              </pre>
            </div>
          </div>
        )}
      </div>

      {/* Manual Sync Section - Production */}
      <div className="bg-white rounded-lg shadow p-6 max-w-2xl mb-6">
        <h2 className="text-lg font-semibold text-gray-800 mb-4 flex items-center">
          <span className="bg-green-100 text-green-800 text-xs font-semibold mr-2 px-2.5 py-0.5 rounded">
            PRODUCTION
          </span>
          Manual Sync (Real Data)
        </h2>
        <p className="text-sm text-gray-600 mb-4">
          ซิงค์ข้อมูลจริงจาก Production API - ค่าเริ่มต้นเป็นวันนี้
        </p>

        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Date From
            </label>
            <input
              type="text"
              value={manualDateFrom}
              onChange={(e) => setManualDateFrom(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
              placeholder="YYYY-MM-DDTHH:mm:ss"
            />
            <p className="text-xs text-gray-500 mt-1">
              Format: {today}T00:00:00
            </p>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Date To
            </label>
            <input
              type="text"
              value={manualDateTo}
              onChange={(e) => setManualDateTo(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
              placeholder="YYYY-MM-DDTHH:mm:ss"
            />
            <p className="text-xs text-gray-500 mt-1">
              Format: {today}T23:59:59 (สูงสุด 30 วัน)
            </p>
          </div>

          <button
            onClick={handleManualSync}
            disabled={manualLoading}
            className={`w-full bg-green-600 text-white px-4 py-2 rounded-md font-medium ${
              manualLoading
                ? "opacity-50 cursor-not-allowed"
                : "hover:bg-green-700"
            }`}
          >
            {manualLoading ? "Syncing..." : "🚀 Manual Sync (Production)"}
          </button>
        </div>

        {manualError && (
          <div className="mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
            <p className="font-medium">Error:</p>
            <p>{manualError}</p>
          </div>
        )}

        {manualResult && (
          <div className="mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            <p className="font-medium mb-2">✅ Manual Sync Success!</p>
            <div className="text-sm space-y-1">
              <p>
                📅 Date Range: {manualResult.date_range?.from} →{" "}
                {manualResult.date_range?.to}
              </p>
              <p>📊 Total Days: {manualResult.total_days} days</p>
              <p>🔍 Found: {manualResult.total_found} bookings</p>
              <p>✨ New: {manualResult.total_new} bookings</p>
              <p>🔄 Updated: {manualResult.total_updated} bookings</p>
              <p className="text-xs text-gray-600 mt-2 pt-2 border-t border-green-300">
                Sync ID: {manualResult.sync_id}
              </p>
            </div>
          </div>
        )}
      </div>

      {/* Test Sync Section */}
      <div className="bg-white rounded-lg shadow p-6 max-w-2xl">
        <h2 className="text-lg font-semibold text-gray-800 mb-4 flex items-center">
          <span className="bg-yellow-100 text-yellow-800 text-xs font-semibold mr-2 px-2.5 py-0.5 rounded">
            TEST API
          </span>
          Test Sync (Arrivals)
        </h2>
        <p className="text-sm text-gray-600 mb-4">
          ทดสอบดึงข้อมูล Booking จาก Holiday Taxis Test API
        </p>

        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Date From (Arrival)
            </label>
            <input
              type="text"
              value={dateFrom}
              onChange={(e) => setDateFrom(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-cyan-500"
              placeholder="YYYY-MM-DDTHH:mm:ss"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Date To (Arrival)
            </label>
            <input
              type="text"
              value={dateTo}
              onChange={(e) => setDateTo(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-cyan-500"
              placeholder="YYYY-MM-DDTHH:mm:ss"
            />
          </div>

          <button
            onClick={handleSync}
            disabled={loading}
            className={`w-full ${
              COMPANY.colors.primary
            } text-white px-4 py-2 rounded-md ${
              loading
                ? "opacity-50 cursor-not-allowed"
                : COMPANY.colors.primaryHover
            }`}
          >
            {loading ? "Syncing..." : "Sync Test Bookings"}
          </button>
        </div>

        {error && (
          <div className="mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
            <p className="font-medium">Error:</p>
            <p>{error}</p>
          </div>
        )}

        {result && (
          <div className="mt-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
            <p className="font-medium mb-2">Sync Success!</p>
            <div className="text-sm space-y-1">
              <p>Found: {result.totalFound} bookings</p>
              <p>New: {result.totalNew} bookings</p>
              <p>Updated: {result.totalUpdated} bookings</p>
              <p>Detail Synced: {result.totalDetailed} bookings</p>
              <p className="text-xs text-gray-600 mt-2">
                {result.dateRange.from} → {result.dateRange.to}
              </p>
            </div>
          </div>
        )}
      </div>

      {/* Check Booking Section */}
      <div className="bg-white rounded-lg shadow p-6 max-w-2xl mt-6">
        <h2 className="text-lg font-semibold text-gray-800 mb-4">
          Check Booking from Holiday Taxis
        </h2>
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Booking Reference
            </label>
            <input
              type="text"
              value={bookingRef}
              onChange={(e) => setBookingRef(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-cyan-500"
              placeholder="e.g., TCS-25581676"
            />
          </div>

          <button
            onClick={handleCheckBooking}
            disabled={checkLoading}
            className={`w-full bg-purple-600 text-white px-4 py-2 rounded-md ${
              checkLoading
                ? "opacity-50 cursor-not-allowed"
                : "hover:bg-purple-700"
            }`}
          >
            {checkLoading ? "Checking..." : "Check Booking"}
          </button>
        </div>

        {checkError && (
          <div className="mt-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
            <p className="font-medium">Error:</p>
            <p>{checkError}</p>
          </div>
        )}

        {bookingData && (
          <div className="mt-4 p-4 bg-cyan-50 border border-cyan-200 rounded">
            <p className="font-semibold text-cyan-900 mb-3">Booking Data:</p>
            <div className="space-y-2 text-sm">
              <div className="grid grid-cols-2 gap-2">
                <span className="font-medium text-gray-700">Ref:</span>
                <span className="text-gray-900">{bookingData.ref}</span>

                <span className="font-medium text-gray-700">Status:</span>
                <span className="text-gray-900">{bookingData.status}</span>

                <span className="font-medium text-gray-700">Passenger:</span>
                <span className="text-gray-900">
                  {bookingData.passenger || "-"}
                </span>

                <span className="font-medium text-gray-700">Vehicle:</span>
                <span className="text-gray-900">
                  {bookingData.vehicle || "-"}
                </span>
              </div>

              {bookingData.driver && (
                <div className="mt-3 pt-3 border-t border-cyan-200">
                  <p className="font-medium text-cyan-900 mb-2">Driver Info:</p>
                  <div className="grid grid-cols-2 gap-2">
                    <span className="font-medium text-gray-700">Name:</span>
                    <span className="text-gray-900">
                      {bookingData.driver.name || "-"}
                    </span>

                    <span className="font-medium text-gray-700">Phone:</span>
                    <span className="text-gray-900">
                      {bookingData.driver.phone || "-"}
                    </span>
                  </div>
                </div>
              )}

              {bookingData.vehicleInfo && (
                <div className="mt-3 pt-3 border-t border-cyan-200">
                  <p className="font-medium text-cyan-900 mb-2">
                    Vehicle Info:
                  </p>
                  <div className="grid grid-cols-2 gap-2">
                    <span className="font-medium text-gray-700">
                      Registration:
                    </span>
                    <span className="text-gray-900">
                      {bookingData.vehicleInfo.registration || "-"}
                    </span>

                    <span className="font-medium text-gray-700">Model:</span>
                    <span className="text-gray-900">
                      {bookingData.vehicleInfo.model || "-"}
                    </span>
                  </div>
                </div>
              )}

              {bookingData.tracking && (
                <div className="mt-3 pt-3 border-t border-cyan-200">
                  <p className="font-medium text-cyan-900 mb-2">
                    Tracking Status:
                  </p>
                  <span className="text-gray-900">
                    {bookingData.tracking.status || "-"}
                  </span>
                </div>
              )}

              <div className="mt-3 pt-3 border-t border-cyan-200">
                <button
                  onClick={() => {
                    const formatted = JSON.stringify(bookingData.raw, null, 2);
                    navigator.clipboard.writeText(formatted);
                    alert("Full data copied to clipboard!");
                  }}
                  className="text-xs text-cyan-600 hover:text-cyan-800 underline"
                >
                  Copy Full Data (JSON)
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
