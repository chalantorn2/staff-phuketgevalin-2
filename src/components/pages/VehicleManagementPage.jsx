// src/components/pages/VehicleManagementPage.jsx
import { useState, useEffect, useRef } from "react";
import { getCompanyClass } from "../../config/company";

// API Client
const apiCall = async (endpoint, options = {}) => {
  const url = `/api${endpoint}`;
  const config = {
    headers: {
      "Content-Type": "application/json",
      ...options.headers,
    },
    ...options,
  };

  try {
    const response = await fetch(url, config);
    const data = await response.json();
    return data;
  } catch (error) {
    console.error("API call failed:", error);
    return { success: false, message: "Network error" };
  }
};

function VehicleManagementPage() {
  const [vehicles, setVehicles] = useState([]);
  const [drivers, setDrivers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editingVehicle, setEditingVehicle] = useState(null);
  const [formData, setFormData] = useState({
    registration: "",
    brand: "",
    model: "",
    color: "",
    description: "",
    default_driver_id: "",
    status: "active",
  });
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const formRef = useRef(null);
  const [searchQuery, setSearchQuery] = useState("");

  useEffect(() => {
    fetchVehicles();
    fetchDrivers();
  }, []);

  const fetchVehicles = async () => {
    try {
      setLoading(true);
      const result = await apiCall("/vehicles/manage.php");

      if (result.success) {
        setVehicles(result.data);
      } else {
        setError(result.message || "Failed to fetch vehicles");
      }
    } catch (err) {
      setError("Network error. Please try again.");
    } finally {
      setLoading(false);
    }
  };

  const fetchDrivers = async () => {
    try {
      const result = await apiCall("/drivers/manage.php");
      if (result.success) {
        setDrivers(result.data.filter((driver) => driver.status === "active"));
      }
    } catch (err) {
      console.error("Failed to fetch drivers:", err);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setSuccess("");

    try {
      const endpoint = editingVehicle
        ? `/vehicles/manage.php?id=${editingVehicle.id}`
        : `/vehicles/manage.php`;

      const method = editingVehicle ? "PUT" : "POST";

      const result = await apiCall(endpoint, {
        method,
        body: JSON.stringify(formData),
      });

      if (result.success) {
        setSuccess(result.message || "Operation completed successfully");
        setShowForm(false);
        setEditingVehicle(null);
        resetForm();
        fetchVehicles();
      } else {
        setError(result.message || "Operation failed");
      }
    } catch (err) {
      setError("Network error. Please try again.");
    }
  };

  const handleEdit = (vehicle) => {
    setEditingVehicle(vehicle);
    setFormData({
      registration: vehicle.registration,
      brand: vehicle.brand || "",
      model: vehicle.model || "",
      color: vehicle.color || "",
      description: vehicle.description || "",
      default_driver_id: vehicle.default_driver_id || "",
      status: vehicle.status,
    });
    setShowForm(true);

    // Scroll ไปที่ฟอร์ม
    setTimeout(() => {
      formRef.current?.scrollIntoView({ behavior: "smooth", block: "start" });
    }, 100);
  };

  const handleDelete = async (vehicleId) => {
    if (!confirm("Are you sure you want to delete this vehicle?")) return;

    try {
      const result = await apiCall(`/vehicles/manage.php?id=${vehicleId}`, {
        method: "DELETE",
      });

      if (result.success) {
        setSuccess("Vehicle deleted successfully");

        // ปิดฟอร์มถ้ากำลังแก้ไขรถที่ถูกลบ
        if (editingVehicle && editingVehicle.id === vehicleId) {
          resetForm();
        }

        // รีเฟรชรายการรถ
        fetchVehicles();
      } else {
        setError(result.message || "Failed to delete vehicle");
      }
    } catch (err) {
      setError("Network error. Please try again.");
    }
  };

  const resetForm = () => {
    setFormData({
      registration: "",
      brand: "",
      model: "",
      color: "",
      description: "",
      default_driver_id: "",
      status: "active",
    });
    setEditingVehicle(null);
    setShowForm(false);
  };

  const formatDate = (dateString) => {
    if (!dateString) return "-";
    return new Date(dateString).toLocaleDateString("en-GB");
  };

  // Filter vehicles based on search query
  const filteredVehicles = vehicles.filter((vehicle) => {
    if (!searchQuery) return true;
    const query = searchQuery.toLowerCase();
    return (
      vehicle.registration?.toLowerCase().includes(query) ||
      vehicle.brand?.toLowerCase().includes(query) ||
      vehicle.model?.toLowerCase().includes(query) ||
      vehicle.color?.toLowerCase().includes(query) ||
      vehicle.default_driver_name?.toLowerCase().includes(query)
    );
  });

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">
            Vehicle Management
          </h1>
          <p className="text-gray-600 mt-1">จัดการข้อมูลรถทั้งหมด</p>
        </div>
        <button
          onClick={() => {
            setShowForm(true);
            setTimeout(() => {
              formRef.current?.scrollIntoView({
                behavior: "smooth",
                block: "start",
              });
            }, 100);
          }}
          className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white bg-cyan-600 hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500 shadow-sm transition-colors"
        >
          <i className="fas fa-plus"></i>
          Add Vehicle
        </button>
      </div>

      {/* Messages */}
      {error && (
        <div className="bg-red-50 border border-red-200 rounded-lg p-4">
          <p className="text-sm text-red-800">{error}</p>
        </div>
      )}

      {success && (
        <div className="bg-green-50 border border-green-200 rounded-lg p-4">
          <p className="text-sm text-green-800">{success}</p>
        </div>
      )}

      {/* Add/Edit Form */}
      {showForm && (
        <div
          ref={formRef}
          className="bg-white rounded-xl shadow-sm border border-gray-200 p-4"
        >
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-base font-semibold text-gray-900">
              {editingVehicle ? "แก้ไขข้อมูลรถ" : "เพิ่มรถใหม่"}
            </h2>
            <button
              onClick={resetForm}
              className="text-gray-400 hover:text-gray-600"
            >
              <i className="fas fa-times text-lg"></i>
            </button>
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            {/* แถวแรก - 3 คอลัมน์ */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  ทะเบียนรถ *
                </label>
                <input
                  type="text"
                  required
                  placeholder="กข-1234 หรือ ABC-123"
                  value={formData.registration}
                  onChange={(e) =>
                    setFormData({
                      ...formData,
                      registration: e.target.value.toUpperCase(),
                    })
                  }
                  className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  ยี่ห้อ
                </label>
                <input
                  type="text"
                  placeholder="Toyota, Honda, Isuzu..."
                  value={formData.brand}
                  onChange={(e) =>
                    setFormData({ ...formData, brand: e.target.value })
                  }
                  className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  รุ่น
                </label>
                <input
                  type="text"
                  placeholder="Vios, Civic, D-Max..."
                  value={formData.model}
                  onChange={(e) =>
                    setFormData({ ...formData, model: e.target.value })
                  }
                  className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
                />
              </div>
            </div>

            {/* แถวสอง - 3 คอลัมน์ */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  สี
                </label>
                <input
                  type="text"
                  placeholder="ขาว, เงิน, ดำ..."
                  value={formData.color}
                  onChange={(e) =>
                    setFormData({ ...formData, color: e.target.value })
                  }
                  className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  คนขับประจำ
                </label>
                <select
                  value={formData.default_driver_id}
                  onChange={(e) =>
                    setFormData({
                      ...formData,
                      default_driver_id: e.target.value,
                    })
                  }
                  className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
                >
                  <option value="">ไม่กำหนด</option>
                  {drivers.map((driver) => (
                    <option key={driver.id} value={driver.id}>
                      {driver.name} ({driver.phone_number})
                    </option>
                  ))}
                </select>
              </div>

              {editingVehicle && (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    สถานะ
                  </label>
                  <select
                    value={formData.status}
                    onChange={(e) =>
                      setFormData({ ...formData, status: e.target.value })
                    }
                    className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
                  >
                    <option value="active">ใช้งานอยู่</option>
                    <option value="maintenance">ซ่อมบำรุง</option>
                    <option value="inactive">ไม่ใช้งาน</option>
                  </select>
                </div>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                คำอธิบายเพิ่มเติม
              </label>
              <textarea
                rows="2"
                placeholder="รายละเอียดเพิ่มเติม เช่น จำนวนที่นั่ง, อุปกรณ์พิเศษ..."
                value={formData.description}
                onChange={(e) =>
                  setFormData({ ...formData, description: e.target.value })
                }
                className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
              />
            </div>

            <div className="flex gap-2 justify-between items-center">
              {/* ปุ่มซ้าย: Save & Cancel */}
              <div className="flex gap-2">
                <button
                  type="submit"
                  className={`px-3 py-1.5 text-sm font-medium rounded-lg text-white transition-colors ${getCompanyClass(
                    "primary"
                  )} ${getCompanyClass("primaryHover")}`}
                >
                  {editingVehicle ? "บันทึกการแก้ไข" : "เพิ่มรถ"}
                </button>
                <button
                  type="button"
                  onClick={resetForm}
                  className="px-3 py-1.5 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                  ยกเลิก
                </button>
              </div>

              {/* ปุ่มขวา: Delete (แสดงเฉพาะตอน Edit) */}
              {editingVehicle && (
                <button
                  type="button"
                  onClick={() => handleDelete(editingVehicle.id)}
                  className="px-3 py-1.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors flex items-center gap-2"
                >
                  <i className="fas fa-trash text-sm"></i>
                  ลบรถ
                </button>
              )}
            </div>
          </form>
        </div>
      )}

      {/* Search Bar */}
      <div className="flex items-center gap-3">
        <div className="flex-1 relative">
          <i className="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
          <input
            type="text"
            placeholder="ค้นหาทะเบียน, ยี่ห้อ, รุ่น, สี, คนขับ..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full pl-10 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
          />
          {searchQuery && (
            <button
              onClick={() => setSearchQuery("")}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
            >
              <i className="fas fa-times"></i>
            </button>
          )}
        </div>
        <div className="text-sm text-gray-500">
          {filteredVehicles.length} / {vehicles.length} คัน
        </div>
      </div>

      {/* Vehicles Table */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        {loading ? (
          <div className="text-center py-12">
            <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <i className="fas fa-spinner animate-spin text-2xl text-gray-400"></i>
            </div>
            <p className="text-gray-500 font-medium">Loading vehicles...</p>
          </div>
        ) : vehicles.length === 0 ? (
          <div className="text-center py-12">
            <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <i className="fas fa-car text-2xl text-gray-400"></i>
            </div>
            <p className="text-gray-500 font-medium">No vehicles found</p>
            <p className="text-sm text-gray-400 mt-1">
              Add your first vehicle to get started
            </p>
          </div>
        ) : filteredVehicles.length === 0 ? (
          <div className="text-center py-12">
            <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <i className="fas fa-search text-2xl text-gray-400"></i>
            </div>
            <p className="text-gray-500 font-medium">ไม่พบรถที่ค้นหา</p>
            <p className="text-sm text-gray-400 mt-1">ลองค้นหาด้วยคำอื่น</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50 border-b border-gray-200">
                <tr>
                  <th className="px-4 py-6 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16"></th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Registration
                  </th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Brand / Model
                  </th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Color
                  </th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Default Driver
                  </th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Added
                  </th>
                  <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {filteredVehicles.map((vehicle, index) => (
                  <tr
                    key={vehicle.id}
                    className="hover:bg-gray-50 transition-colors"
                  >
                    {/* Row Number */}
                    <td className="px-4 py-2 whitespace-nowrap text-center">
                      <div className="text-sm font-medium text-gray-500">
                        {index + 1}
                      </div>
                    </td>

                    {/* Registration */}
                    <td className="px-4 py-2 whitespace-nowrap">
                      <div className="text-sm font-medium text-gray-900">
                        {vehicle.registration}
                      </div>
                    </td>

                    {/* Brand / Model */}
                    <td className="px-4 py-2 whitespace-nowrap">
                      <div className="text-sm text-gray-900">
                        {[vehicle.brand, vehicle.model]
                          .filter(Boolean)
                          .join(" ") || "-"}
                      </div>
                    </td>

                    {/* Color */}
                    <td className="px-4 py-2 whitespace-nowrap">
                      <div className="text-sm text-gray-900">
                        {vehicle.color || "-"}
                      </div>
                    </td>

                    {/* Default Driver */}
                    <td className="px-4 py-2">
                      {vehicle.default_driver_name ? (
                        <div className="text-sm">
                          <div className="font-medium text-gray-900">
                            {vehicle.default_driver_name}
                          </div>
                          {vehicle.default_driver_phone && (
                            <div className="text-gray-500 text-xs">
                              {vehicle.default_driver_phone}
                            </div>
                          )}
                        </div>
                      ) : (
                        <span className="text-sm text-gray-400">-</span>
                      )}
                    </td>

                    {/* Status */}
                    <td className="px-4 py-2 whitespace-nowrap">
                      <span
                        className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ${
                          vehicle.status === "active"
                            ? "bg-green-100 text-green-800"
                            : vehicle.status === "maintenance"
                            ? "bg-yellow-100 text-yellow-800"
                            : "bg-gray-100 text-gray-600"
                        }`}
                      >
                        <span
                          className={`w-1.5 h-1.5 rounded-full ${
                            vehicle.status === "active"
                              ? "bg-green-500"
                              : vehicle.status === "maintenance"
                              ? "bg-yellow-400"
                              : "bg-gray-400"
                          }`}
                        />
                        {vehicle.status === "active"
                          ? "Active"
                          : vehicle.status === "maintenance"
                          ? "ซ่อมบำรุง"
                          : "Inactive"}
                      </span>
                    </td>

                    {/* Added Date */}
                    <td className="px-4 py-2 whitespace-nowrap">
                      <div className="text-sm text-gray-500">
                        {formatDate(vehicle.created_at)}
                      </div>
                    </td>

                    {/* Actions */}
                    <td className="px-4 py-2 whitespace-nowrap text-right">
                      <button
                        onClick={() => handleEdit(vehicle)}
                        className="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-cyan-700 bg-cyan-50 hover:bg-cyan-100 rounded-lg transition-colors border border-cyan-200"
                      >
                        <i className="fas fa-edit text-sm" />
                        Edit
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}

export default VehicleManagementPage;
