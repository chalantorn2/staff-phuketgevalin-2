// src/components/modals/EditProvinceModal.jsx
import { useState, useEffect } from "react";

function EditProvinceModal({ booking, provinces, onClose, onSave }) {
  const [selectedProvince, setSelectedProvince] = useState(
    booking.province || ""
  );
  const [loading, setLoading] = useState(false);
  const [redetecting, setRedetecting] = useState(false);

  const handleSave = async () => {
    if (!selectedProvince) {
      alert("Please select a province");
      return;
    }

    setLoading(true);
    try {
      const response = await fetch(
        `${
          import.meta.env.VITE_API_BASE_URL ||
          "https://staff.phuketgevalin.com/api"
        }/bookings/update-province.php`,
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            booking_ref: booking.ref,
            action: "manual",
            province: selectedProvince,
          }),
        }
      );

      const data = await response.json();

      if (data.success) {
        onSave(data.data);
        onClose();
      } else {
        alert(data.error || "Failed to update province");
      }
    } catch (error) {
      console.error("Error updating province:", error);
      alert("Error updating province");
    } finally {
      setLoading(false);
    }
  };

  const handleRedetect = async () => {
    if (
      !confirm(
        "Re-detect province from booking data? This will override the current province."
      )
    ) {
      return;
    }

    setRedetecting(true);
    try {
      const response = await fetch(
        `${
          import.meta.env.VITE_API_BASE_URL ||
          "https://staff.phuketgevalin.com/api"
        }/bookings/update-province.php`,
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            booking_ref: booking.ref,
            action: "redetect",
          }),
        }
      );

      const data = await response.json();

      if (data.success) {
        setSelectedProvince(data.data.province || "");
        alert(
          data.data.province
            ? `Province detected: ${data.data.province} (${data.data.source})`
            : "Province could not be detected. Please select manually."
        );
        onSave(data.data);
        onClose();
      } else {
        alert(data.error || "Failed to re-detect province");
      }
    } catch (error) {
      console.error("Error re-detecting province:", error);
      alert("Error re-detecting province");
    } finally {
      setRedetecting(false);
    }
  };

  return (
    <div
      className="fixed inset-0 flex items-center justify-center z-50 p-4"
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
        className="bg-white rounded-xl shadow-xl max-w-md w-full"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
          <h3 className="text-lg font-semibold text-gray-900">
            <i className="fas fa-map-marker-alt text-cyan-600 mr-2"></i>
            Edit Province
          </h3>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 transition-colors"
          >
            <i className="fas fa-times text-xl"></i>
          </button>
        </div>

        {/* Body */}
        <div className="p-6 space-y-4">
          {/* Booking Info */}
          <div className="bg-gray-50 rounded-lg p-4">
            <p className="text-sm text-gray-600">Booking Reference</p>
            <p className="font-semibold text-gray-900">{booking.ref}</p>
            <p className="text-sm text-gray-600 mt-2">Passenger</p>
            <p className="font-medium text-gray-900">
              {booking.passenger?.name || "-"}
            </p>
          </div>

          {/* Current Province Info */}
          {booking.province && (
            <div className="bg-cyan-50 border border-cyan-200 rounded-lg p-4">
              <p className="text-sm text-cyan-900 font-medium mb-2">
                Current Province:
              </p>
              <div className="flex items-center gap-2">
                <span
                  className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${
                    booking.province_source === "airport"
                      ? "bg-green-100 text-green-800"
                      : booking.province_source === "postal"
                      ? "bg-yellow-100 text-yellow-800"
                      : booking.province_source === "manual"
                      ? "bg-cyan-100 text-cyan-800"
                      : "bg-gray-100 text-gray-600"
                  }`}
                >
                  <i
                    className={`fas ${
                      booking.province_source === "airport"
                        ? "fa-plane"
                        : booking.province_source === "postal"
                        ? "fa-mail-bulk"
                        : booking.province_source === "manual"
                        ? "fa-user-edit"
                        : "fa-question"
                    } mr-1.5`}
                  ></i>
                  {booking.province}
                </span>
                <span className="text-xs text-cyan-700">
                  (Source: {booking.province_source || "unknown"})
                </span>
              </div>
            </div>
          )}

          {/* Province Selector */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Select Province *
            </label>
            <select
              value={selectedProvince}
              onChange={(e) => setSelectedProvince(e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 transition-colors"
              disabled={loading || redetecting}
            >
              <option value="">-- Select Province --</option>
              {provinces.map((province) => (
                <option key={province} value={province}>
                  {province}
                </option>
              ))}
            </select>
          </div>

          {/* Re-detect Button */}
          <button
            onClick={handleRedetect}
            disabled={loading || redetecting}
            className="w-full px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-lg hover:bg-gray-200 disabled:opacity-50 transition-colors"
          >
            {redetecting ? (
              <>
                <i className="fas fa-spinner animate-spin mr-2"></i>
                Re-detecting...
              </>
            ) : (
              <>
                <i className="fas fa-sync-alt mr-2"></i>
                Auto Re-detect Province
              </>
            )}
          </button>
        </div>

        {/* Footer */}
        <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
          <button
            onClick={onClose}
            disabled={loading || redetecting}
            className="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-white disabled:opacity-50 transition-colors"
          >
            Cancel
          </button>
          <button
            onClick={handleSave}
            disabled={loading || redetecting || !selectedProvince}
            className="px-4 py-2 text-sm font-medium text-white bg-cyan-600 rounded-lg hover:bg-cyan-700 disabled:opacity-50 transition-colors"
          >
            {loading ? (
              <>
                <i className="fas fa-spinner animate-spin mr-2"></i>
                Saving...
              </>
            ) : (
              <>
                <i className="fas fa-save mr-2"></i>
                Save Province
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
}

export default EditProvinceModal;
