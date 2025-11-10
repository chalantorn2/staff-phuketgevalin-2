// src/components/modals/ChangeTimeModal.jsx
import React, { useState, useEffect } from "react";
import { Clock, X } from "lucide-react";

function ChangeTimeModal({ isOpen, onClose, assignment, onSuccess }) {
  const [newDate, setNewDate] = useState("");
  const [newHour, setNewHour] = useState("");
  const [newMinute, setNewMinute] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  // Initialize with current pickup date/time when modal opens
  useEffect(() => {
    if (isOpen && assignment) {
      const pickupDate =
        assignment.booking.pickup_date_adjusted ||
        assignment.booking.pickup_date;
      if (pickupDate) {
        const date = new Date(pickupDate);
        // Format date as YYYY-MM-DD (using local timezone, not UTC)
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        const dateStr = `${year}-${month}-${day}`;

        // Format time as HH:MM (using local timezone)
        const hour = String(date.getHours()).padStart(2, "0");
        const minute = String(date.getMinutes()).padStart(2, "0");

        setNewDate(dateStr);
        setNewHour(hour);
        setNewMinute(minute);
      }
      setError(null);
    }
  }, [isOpen, assignment]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      // Validate hour and minute
      const hour = parseInt(newHour, 10);
      const minute = parseInt(newMinute, 10);

      if (isNaN(hour) || hour < 0 || hour > 23) {
        throw new Error("ชั่วโมงต้องอยู่ระหว่าง 0-23");
      }

      if (isNaN(minute) || minute < 0 || minute > 59) {
        throw new Error("นาทีต้องอยู่ระหว่าง 00-59");
      }

      // Format to HH:MM
      const formattedHour = String(hour).padStart(2, "0");
      const formattedMinute = String(minute).padStart(2, "0");

      // Combine date and time
      const newPickupDateTime = `${newDate} ${formattedHour}:${formattedMinute}:00`;

      const response = await fetch("/api/bookings/adjust-time.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          booking_ref: assignment.booking_ref,
          new_pickup_date: newPickupDateTime,
        }),
      });

      const data = await response.json();

      if (!data.success) {
        throw new Error(data.message || "Failed to adjust time");
      }

      // Success
      onSuccess && onSuccess(data.data);
      onClose();
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  if (!isOpen || !assignment) return null;

  const originalPickupDate = assignment.booking.pickup_date;
  const currentDisplayDate =
    assignment.booking.pickup_date_adjusted || originalPickupDate;

  return (
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
      onClick={onClose}
    >
      <div
        className="bg-white rounded-xl shadow-xl"
        style={{
          width: "90%",
          maxWidth: "500px",
          maxHeight: "85vh",
          display: "flex",
          flexDirection: "column",
        }}
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
          <h3 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
            <Clock size={20} className="text-orange-600" />
            Change Time
          </h3>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 transition-colors"
          >
            <X size={20} />
          </button>
        </div>

        {/* Body */}
        <form
          onSubmit={handleSubmit}
          className="p-6 space-y-4"
          style={{
            flex: 1,
            minHeight: 0,
            overflow: "auto",
          }}
        >
          {/* Booking Info */}
          <div className="bg-gray-50 rounded-lg p-4 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-600">Booking:</span>
              <span className="text-sm font-semibold text-gray-900">
                {assignment.booking_ref}
              </span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-600">ผู้โดยสาร:</span>
              <span className="text-sm font-medium text-gray-900">
                {assignment.booking.passenger_name}
              </span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-600">เวลาปัจจุบัน:</span>
              <span className="text-sm font-medium text-gray-900">
                {new Date(currentDisplayDate).toLocaleString("th-TH", {
                  day: "2-digit",
                  month: "short",
                  year: "numeric",
                  hour: "2-digit",
                  minute: "2-digit",
                })}
              </span>
            </div>
          </div>

          <div className="border-t border-gray-200 pt-4"></div>

          {/* Date and Time Input - Same Row */}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                วันที่ใหม่ *
              </label>
              <input
                type="date"
                value={newDate}
                onChange={(e) => setNewDate(e.target.value)}
                required
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                เวลาใหม่ *
              </label>
              <div className="flex items-center gap-2">
                <input
                  type="number"
                  value={newHour}
                  onChange={(e) => setNewHour(e.target.value)}
                  placeholder="ชม"
                  min="0"
                  max="23"
                  required
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500 text-center"
                />
                <span className="text-gray-500 font-semibold">:</span>
                <input
                  type="number"
                  value={newMinute}
                  onChange={(e) => setNewMinute(e.target.value)}
                  placeholder="นาที"
                  min="0"
                  max="59"
                  required
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500 text-center"
                />
              </div>
            </div>
          </div>

          {/* Error Message */}
          {error && (
            <div className="bg-red-50 border border-red-200 rounded-lg p-3">
              <p className="text-sm text-red-800">{error}</p>
            </div>
          )}
        </form>

        {/* Modal Footer */}
        <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-white"
            disabled={loading}
          >
            ยกเลิก
          </button>
          <button
            type="submit"
            onClick={handleSubmit}
            className="px-4 py-2 text-sm font-medium text-white bg-orange-600 rounded-lg hover:bg-orange-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            disabled={loading}
          >
            {loading ? (
              <>
                <i className="fas fa-spinner fa-spin"></i>
                กำลังบันทึก...
              </>
            ) : (
              <>
                <Clock size={16} />
                บันทึกเวลาใหม่
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
}

export default ChangeTimeModal;
