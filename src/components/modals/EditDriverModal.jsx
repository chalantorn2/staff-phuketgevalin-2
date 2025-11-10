// src/components/modals/EditDriverModal.jsx
import React, { useState, useEffect, useRef } from "react";
import { UserCog, X, Plus } from "lucide-react";
import AddDriverModal from "./AddDriverModal";
import AddVehicleModal from "./AddVehicleModal";

function EditDriverModal({ isOpen, onClose, assignment, onSuccess }) {
  const [drivers, setDrivers] = useState([]);
  const [vehicles, setVehicles] = useState([]);
  const [driverSearchTerm, setDriverSearchTerm] = useState("");
  const [vehicleSearchTerm, setVehicleSearchTerm] = useState("");
  const [showDriverDropdown, setShowDriverDropdown] = useState(false);
  const [showVehicleDropdown, setShowVehicleDropdown] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const [assignmentForm, setAssignmentForm] = useState({
    driver_id: "",
    vehicle_id: "",
    notes: "",
  });

  // Modals for adding new driver/vehicle
  const [showAddDriverModal, setShowAddDriverModal] = useState(false);
  const [showAddVehicleModal, setShowAddVehicleModal] = useState(false);

  // Refs for click outside detection
  const driverDropdownRef = useRef(null);
  const vehicleDropdownRef = useRef(null);

  // Fetch drivers and vehicles
  useEffect(() => {
    if (isOpen) {
      fetchDrivers();
      fetchVehicles();

      // Set current assignment data
      if (assignment) {
        setAssignmentForm({
          driver_id: assignment.driver?.id || "",
          vehicle_id: assignment.vehicle?.id || "",
          notes: assignment.assignment_notes || "",
        });
        setDriverSearchTerm(assignment.driver?.name || "");
        setVehicleSearchTerm(assignment.vehicle?.registration || "");
      }
    }
  }, [isOpen, assignment]);

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

  const fetchDrivers = async () => {
    try {
      const response = await fetch("/api/drivers/manage.php");
      const data = await response.json();
      if (data.success) {
        setDrivers(data.data);
      }
    } catch (err) {
      console.error("Error fetching drivers:", err);
    }
  };

  const fetchVehicles = async () => {
    try {
      const response = await fetch("/api/vehicles/manage.php");
      const data = await response.json();
      if (data.success) {
        setVehicles(data.data);
      }
    } catch (err) {
      console.error("Error fetching vehicles:", err);
    }
  };

  // Filter drivers based on search
  const filteredDrivers = drivers.filter(
    (driver) =>
      (driver.name || "")
        .toLowerCase()
        .includes(driverSearchTerm.toLowerCase()) ||
      (driver.phone_number || "").includes(driverSearchTerm)
  );

  // Filter vehicles based on search
  const filteredVehicles = vehicles.filter(
    (vehicle) =>
      (vehicle.registration || "")
        .toLowerCase()
        .includes(vehicleSearchTerm.toLowerCase()) ||
      (vehicle.brand || "")
        .toLowerCase()
        .includes(vehicleSearchTerm.toLowerCase()) ||
      (vehicle.model || "")
        .toLowerCase()
        .includes(vehicleSearchTerm.toLowerCase())
  );

  const handleSelectDriver = (driver) => {
    setAssignmentForm({ ...assignmentForm, driver_id: driver.id });
    setDriverSearchTerm(driver.name || "");
    setShowDriverDropdown(false);
  };

  const handleSelectVehicle = (vehicle) => {
    setAssignmentForm({ ...assignmentForm, vehicle_id: vehicle.id });
    setVehicleSearchTerm(vehicle.registration || "");
    setShowVehicleDropdown(false);
  };

  const handleSubmit = async () => {
    if (!assignmentForm.driver_id || !assignmentForm.vehicle_id) {
      alert("Please select driver and vehicle");
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const response = await fetch("/api/assignments/assign.php", {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          "X-Method": "PUT",
        },
        body: JSON.stringify({
          assignment_id: assignment.id,
          driver_id: assignmentForm.driver_id,
          vehicle_id: assignmentForm.vehicle_id,
          notes: assignmentForm.notes,
        }),
      });

      const data = await response.json();

      if (!data.success) {
        throw new Error(data.message || "Failed to update assignment");
      }

      // Success
      onSuccess && onSuccess();
      onClose();
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const handleAddDriverSuccess = (newDriver) => {
    // Refresh drivers list
    fetchDrivers();
    // Auto-select the new driver
    setTimeout(() => {
      handleSelectDriver(newDriver);
    }, 100);
  };

  const handleAddVehicleSuccess = (newVehicle) => {
    // Refresh vehicles list
    fetchVehicles();
    // Auto-select the new vehicle
    setTimeout(() => {
      handleSelectVehicle(newVehicle);
    }, 100);
  };

  if (!isOpen || !assignment) return null;

  return (
    <>
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
            maxWidth: "600px",
            maxHeight: "85vh",
            display: "flex",
            flexDirection: "column",
          }}
          onClick={(e) => e.stopPropagation()}
        >
          {/* Header */}
          <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 className="text-lg font-semibold text-gray-900 flex items-center gap-2">
              <UserCog size={20} className="text-cyan-600" />
              Change Driver & Vehicle
            </h3>
            <button
              onClick={onClose}
              className="text-gray-400 hover:text-gray-600 transition-colors"
            >
              <X size={20} />
            </button>
          </div>

          {/* Body */}
          <div
            className="p-6 space-y-4"
            style={{
              flex: 1,
              minHeight: 0,
              overflow: "auto",
            }}
          >
            {/* Current Assignment Info */}
            <div className="bg-cyan-50 border border-cyan-200 rounded-lg p-4">
              <p className="text-sm text-cyan-900 font-medium mb-2">
                Current Assignment:
              </p>
              <p className="text-sm text-cyan-800">
                <i className="fas fa-user mr-2"></i>
                {assignment.driver?.name || "N/A"}
              </p>
              <p className="text-sm text-cyan-800">
                <i className="fas fa-car mr-2"></i>
                {assignment.vehicle?.registration || "N/A"} -{" "}
                {assignment.vehicle?.brand || "N/A"}{" "}
                {assignment.vehicle?.model || "N/A"}
              </p>
            </div>

            {/* Warning if driver has already started */}
            {assignment.status === "in_progress" && (
              <div className="bg-orange-50 border border-orange-200 rounded-lg p-4">
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

            {/* Driver Search with Add Button */}
            <div className="relative" ref={driverDropdownRef}>
              <div className="flex items-center justify-between mb-2">
                <label className="block text-sm font-medium text-gray-700">
                  Select Driver *
                </label>
                <button
                  type="button"
                  onClick={() => setShowAddDriverModal(true)}
                  className="text-xs text-cyan-600 hover:text-cyan-700 flex items-center gap-1"
                >
                  <Plus size={14} />
                  เพิ่มคนขับใหม่
                </button>
              </div>

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

            {/* Vehicle Search with Add Button */}
            <div className="relative" ref={vehicleDropdownRef}>
              <div className="flex items-center justify-between mb-2">
                <label className="block text-sm font-medium text-gray-700">
                  Select Vehicle *
                </label>
                <button
                  type="button"
                  onClick={() => setShowAddVehicleModal(true)}
                  className="text-xs text-cyan-600 hover:text-cyan-700 flex items-center gap-1"
                >
                  <Plus size={14} />
                  เพิ่มรถใหม่
                </button>
              </div>

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

            {/* Error Message */}
            {error && (
              <div className="bg-red-50 border border-red-200 rounded-lg p-3">
                <p className="text-sm text-red-800">{error}</p>
              </div>
            )}
          </div>

          {/* Footer */}
          <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-white"
              disabled={loading}
            >
              Cancel
            </button>
            <button
              type="button"
              onClick={handleSubmit}
              className="px-4 py-2 text-sm font-medium text-white bg-cyan-600 rounded-lg hover:bg-cyan-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
              disabled={loading}
            >
              {loading ? (
                <>
                  <i className="fas fa-spinner fa-spin"></i>
                  Updating...
                </>
              ) : (
                <>
                  <i className="fas fa-check"></i>
                  Update Assignment
                </>
              )}
            </button>
          </div>
        </div>
      </div>

      {/* Add Driver Modal */}
      <AddDriverModal
        isOpen={showAddDriverModal}
        onClose={() => setShowAddDriverModal(false)}
        onSuccess={handleAddDriverSuccess}
      />

      {/* Add Vehicle Modal */}
      <AddVehicleModal
        isOpen={showAddVehicleModal}
        onClose={() => setShowAddVehicleModal(false)}
        onSuccess={handleAddVehicleSuccess}
      />
    </>
  );
}

export default EditDriverModal;
