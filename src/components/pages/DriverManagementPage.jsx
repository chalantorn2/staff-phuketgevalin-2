// src/components/pages/DriverManagementPage.jsx
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

function DriverManagementPage() {
  const [drivers, setDrivers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editingDriver, setEditingDriver] = useState(null);
  const [formData, setFormData] = useState({
    name: "",
    phone_number: "+66",
    preferred_contact_method: "VOICE",
    contact_methods: ["VOICE"],
    license_number: "",
    code: "",
    status: "active",
  });
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const formRef = useRef(null);
  const [searchQuery, setSearchQuery] = useState("");

  useEffect(() => {
    fetchDrivers();
  }, []);

  const fetchDrivers = async () => {
    try {
      setLoading(true);
      const result = await apiCall("/drivers/manage.php");

      if (result.success) {
        setDrivers(result.data);
      } else {
        setError(result.message || "Failed to fetch drivers");
      }
    } catch (err) {
      setError("Network error. Please try again.");
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setSuccess("");

    // Trim all string fields
    const trimmedData = {
      ...formData,
      name: formData.name?.trim() || "",
      phone_number: formData.phone_number?.trim() || "",
      license_number: formData.license_number?.trim() || "",
      code: formData.code?.trim() || "",
    };

    console.log("Form data before validation:", trimmedData);

    // Validate required fields for creating new driver (only name and phone)
    if (!editingDriver) {
      const missingFields = [];
      if (!trimmedData.name) missingFields.push("Name");
      if (!trimmedData.phone_number || trimmedData.phone_number === "+66")
        missingFields.push("Phone number");

      if (missingFields.length > 0) {
        setError(`Please fill in: ${missingFields.join(", ")}`);
        console.log("Missing fields:", missingFields);
        return;
      }
    }

    try {
      const endpoint = editingDriver
        ? `/drivers/manage.php?id=${editingDriver.id}`
        : `/drivers/manage.php`;

      const method = editingDriver ? "PUT" : "POST";

      // Prepare data
      const submitData = { ...trimmedData };

      console.log("Sending to API:", { endpoint, method, data: submitData });

      const result = await apiCall(endpoint, {
        method,
        body: JSON.stringify(submitData),
      });

      if (result.success) {
        setSuccess(result.message || "Operation completed successfully");
        setShowForm(false);
        setEditingDriver(null);
        resetForm();
        fetchDrivers();
      } else {
        setError(result.message || "Operation failed");
      }
    } catch (err) {
      setError("Network error. Please try again.");
    }
  };

  const handleEdit = (driver) => {
    setEditingDriver(driver);
    setFormData({
      name: driver.name,
      phone_number: driver.phone_number,
      preferred_contact_method: driver.preferred_contact_method,
      contact_methods: driver.contact_methods || ["VOICE"],
      license_number: driver.license_number || "",
      code: driver.code || "",
      status: driver.status,
    });
    setShowForm(true);

    // Scroll ไปที่ฟอร์ม
    setTimeout(() => {
      formRef.current?.scrollIntoView({ behavior: "smooth", block: "start" });
    }, 100);
  };

  const handleDelete = async (driverId) => {
    if (!confirm("Are you sure you want to delete this driver?")) return;

    try {
      const result = await apiCall(`/drivers/manage.php?id=${driverId}`, {
        method: "DELETE",
      });

      if (result.success) {
        setSuccess("Driver deleted successfully");

        // ปิดฟอร์มถ้ากำลังแก้ไข driver ที่ถูกลบ
        if (editingDriver && editingDriver.id === driverId) {
          resetForm();
        }

        // รีเฟรชรายการ driver
        fetchDrivers();
      } else {
        setError(result.message || "Failed to delete driver");
      }
    } catch (err) {
      setError("Network error. Please try again.");
    }
  };

  const resetForm = () => {
    setFormData({
      name: "",
      phone_number: "+66",
      preferred_contact_method: "VOICE",
      contact_methods: ["VOICE"],
      license_number: "",
      code: "",
      status: "active",
    });
    setEditingDriver(null);
    setShowForm(false);
  };

  const handleContactMethodChange = (method, checked) => {
    if (checked) {
      const newMethods = [...formData.contact_methods, method];
      setFormData({
        ...formData,
        contact_methods: newMethods,
      });
    } else {
      // ไม่ให้ uncheck วิธีหลัก
      if (method === formData.preferred_contact_method) {
        return;
      }
      setFormData({
        ...formData,
        contact_methods: formData.contact_methods.filter((m) => m !== method),
      });
    }
  };

  const handlePreferredMethodChange = (method) => {
    // เพิ่มวิธีหลักเข้าไปใน contact_methods ถ้ายังไม่มี
    const newMethods = formData.contact_methods.includes(method)
      ? formData.contact_methods
      : [...formData.contact_methods, method];

    setFormData({
      ...formData,
      preferred_contact_method: method,
      contact_methods: newMethods,
    });
  };

  const formatDate = (dateString) => {
    if (!dateString) return "-";
    return new Date(dateString).toLocaleDateString("en-GB");
  };

  const getContactMethodIcon = (method) => {
    const icons = {
      VOICE: "fas fa-phone",
      SMS: "fas fa-sms",
      WHATSAPP: "fab fa-whatsapp",
    };
    return icons[method] || "fas fa-phone";
  };

  // Filter drivers based on search query
  const filteredDrivers = drivers.filter((driver) => {
    if (!searchQuery) return true;
    const query = searchQuery.toLowerCase();
    return (
      driver.name?.toLowerCase().includes(query) ||
      driver.phone_number?.toLowerCase().includes(query) ||
      driver.code?.toLowerCase().includes(query) ||
      driver.license_number?.toLowerCase().includes(query) ||
      driver.default_vehicle_registration?.toLowerCase().includes(query)
    );
  });

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">
            Driver Management
          </h1>
          <p className="text-gray-600 mt-1">จัดการข้อมูลคนขับทั้งหมด</p>
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
          Add Driver
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
          className="bg-white rounded-xl shadow-sm border border-cyan-600 p-4"
        >
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-base font-semibold text-gray-900">
              {editingDriver ? "แก้ไขข้อมูลคนขับ" : "เพิ่มคนขับใหม่"}
            </h2>
            <button
              onClick={resetForm}
              className="text-gray-400 hover:text-gray-600"
            >
              <i className="fas fa-times text-lg"></i>
            </button>
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            {/* ข้อมูลพื้นฐาน - 4 คอลัมน์ */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  ชื่อ-นามสกุล *
                </label>
                <input
                  type="text"
                  required
                  value={formData.name}
                  onChange={(e) =>
                    setFormData({ ...formData, name: e.target.value })
                  }
                  className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  เบอร์มือถือ *
                </label>
                <div className="relative">
                  <span className="absolute left-3 top-1.5 text-sm text-gray-500 font-medium">
                    +66
                  </span>
                  <input
                    type="tel"
                    required
                    placeholder="812345678"
                    maxLength="9"
                    value={formData.phone_number.replace("+66", "")}
                    onChange={(e) => {
                      const value = e.target.value.replace(/\D/g, ""); // เอาตัวเลขอย่างเดียว
                      if (value.length <= 9) {
                        setFormData({
                          ...formData,
                          phone_number: "+66" + value,
                        });
                      }
                    }}
                    className="w-full pl-12 pr-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  รหัสคนขับ (Code)
                </label>
                <input
                  type="text"
                  value={formData.code}
                  onChange={(e) =>
                    setFormData({ ...formData, code: e.target.value })
                  }
                  placeholder="เช่น: DRV001"
                  className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  เลขที่ใบขับขี่
                </label>
                <input
                  type="text"
                  value={formData.license_number}
                  onChange={(e) =>
                    setFormData({ ...formData, license_number: e.target.value })
                  }
                  className="w-full px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
                />
              </div>
            </div>

            {/* สถานะ - แถวที่ 2 (แสดงเฉพาะตอน Edit) */}
            {editingDriver && (
              <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
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
                    <option value="inactive">ไม่ใช้งาน</option>
                  </select>
                </div>
              </div>
            )}

            {/* วิธีติดต่อ - 1 แถว */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              {/* วิธีติดต่อหลัก */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  วิธีติดต่อหลัก
                  <span className="text-xs text-gray-500 ml-1 font-normal">
                    (ลูกค้าจะเห็นช่องทางนี้ก่อน)
                  </span>
                </label>
                <div className="grid grid-cols-3 gap-2">
                  {["VOICE", "SMS", "WHATSAPP"].map((method) => (
                    <label
                      key={method}
                      className="flex items-center space-x-2 p-2 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer"
                    >
                      <input
                        type="radio"
                        name="preferred_method"
                        value={method}
                        checked={formData.preferred_contact_method === method}
                        onChange={(e) =>
                          handlePreferredMethodChange(e.target.value)
                        }
                        className="text-cyan-600 focus:ring-cyan-500"
                      />
                      <i
                        className={`${getContactMethodIcon(
                          method
                        )} text-gray-600`}
                      ></i>
                      <span className="text-sm font-medium text-gray-700">
                        {method === "VOICE"
                          ? "โทรเสียง"
                          : method === "SMS"
                          ? "SMS"
                          : "WhatsApp"}
                      </span>
                    </label>
                  ))}
                </div>
              </div>

              {/* วิธีติดต่อสำรอง */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  วิธีติดต่อสำรอง
                  <span className="text-xs text-gray-500 ml-1 font-normal">
                    (ช่องทางเพิ่มเติมที่คนขับรับได้)
                  </span>
                </label>
                <div className="grid grid-cols-3 gap-2">
                  {["VOICE", "SMS", "WHATSAPP"].map((method) => {
                    const isPreferred =
                      method === formData.preferred_contact_method;
                    return (
                      <label
                        key={method}
                        className={`flex items-center space-x-2 p-2 border rounded-lg cursor-pointer ${
                          isPreferred
                            ? "border-cyan-300 bg-cyan-50"
                            : "border-gray-200 hover:bg-gray-50"
                        }`}
                      >
                        <input
                          type="checkbox"
                          checked={formData.contact_methods.includes(method)}
                          onChange={(e) =>
                            handleContactMethodChange(method, e.target.checked)
                          }
                          disabled={isPreferred}
                          className="text-cyan-600 focus:ring-cyan-500 disabled:opacity-50"
                        />
                        <i
                          className={`${getContactMethodIcon(
                            method
                          )} text-gray-600`}
                        ></i>
                        <span
                          className={`text-sm ${
                            isPreferred
                              ? "font-medium text-cyan-700"
                              : "text-gray-700"
                          }`}
                        >
                          {method === "VOICE"
                            ? "โทรเสียง"
                            : method === "SMS"
                            ? "SMS"
                            : "WhatsApp"}
                        </span>
                      </label>
                    );
                  })}
                </div>
              </div>
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
                  {editingDriver ? "บันทึกการแก้ไข" : "เพิ่มคนขับ"}
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
              {editingDriver && (
                <button
                  type="button"
                  onClick={() => handleDelete(editingDriver.id)}
                  className="px-3 py-1.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors flex items-center gap-2"
                >
                  <i className="fas fa-trash text-sm"></i>
                  ลบคนขับ
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
            placeholder="ค้นหาชื่อ, เบอร์โทร, เลขใบขับขี่, ทะเบียนรถ..."
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
          {filteredDrivers.length} / {drivers.length} คน
        </div>
      </div>

      {/* Drivers Table */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        {loading ? (
          <div className="text-center py-12">
            <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <i className="fas fa-spinner animate-spin text-2xl text-gray-400"></i>
            </div>
            <p className="text-gray-500 font-medium">Loading drivers...</p>
          </div>
        ) : drivers.length === 0 ? (
          <div className="text-center py-12">
            <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <i className="fas fa-car-side text-2xl text-gray-400"></i>
            </div>
            <p className="text-gray-500 font-medium">No drivers found</p>
            <p className="text-sm text-gray-400 mt-1">
              Add your first driver to get started
            </p>
          </div>
        ) : filteredDrivers.length === 0 ? (
          <div className="text-center py-12">
            <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <i className="fas fa-search text-2xl text-gray-400"></i>
            </div>
            <p className="text-gray-500 font-medium">ไม่พบคนขับที่ค้นหา</p>
            <p className="text-sm text-gray-400 mt-1">ลองค้นหาด้วยคำอื่น</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50 border-b  border-gray-200">
                <tr>
                  <th className="px-4 py-6 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16"></th>
                  <th className="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-20">
                    Code
                  </th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Driver
                  </th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Phone
                  </th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    License
                  </th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Vehicle
                  </th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Joined
                  </th>
                  <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {filteredDrivers.map((driver, index) => (
                  <tr
                    key={driver.id}
                    className="hover:bg-gray-50 transition-colors"
                  >
                    {/* Row Number */}
                    <td className="px-4 py-2 whitespace-nowrap text-center">
                      <div className="text-sm font-medium text-gray-500">
                        {index + 1}
                      </div>
                    </td>

                    {/* Driver Code */}
                    <td className="px-4 py-2 whitespace-nowrap text-center">
                      <div className="text-sm text-cyan-600 font-medium">
                        {driver.code || "-"}
                      </div>
                    </td>

                    {/* Driver Name */}
                    <td className="px-4 py-2 whitespace-nowrap">
                      <div
                        className="text-sm font-medium text-gray-900"
                        title={driver.name}
                      >
                        {driver.name.length > 20
                          ? driver.name.substring(0, 20) + "..."
                          : driver.name}
                      </div>
                    </td>

                    {/* Phone */}
                    <td className="px-4 py-2 whitespace-nowrap">
                      <div className="text-sm text-gray-900">
                        {driver.phone_number}
                      </div>
                    </td>

                    {/* License */}
                    <td className="px-4 py-2 whitespace-nowrap">
                      <div className="text-sm text-gray-900">
                        {driver.license_number || "-"}
                      </div>
                    </td>

                    {/* Vehicle */}
                    <td className="px-4 py-2">
                      {driver.default_vehicle_registration ? (
                        <div className="text-sm">
                          <div className="font-medium text-gray-900">
                            {driver.default_vehicle_registration}
                          </div>
                          {(driver.default_vehicle_brand ||
                            driver.default_vehicle_model) && (
                            <div className="text-gray-500 text-xs">
                              {driver.default_vehicle_brand}{" "}
                              {driver.default_vehicle_model}
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
                          driver.status === "active"
                            ? "bg-green-100 text-green-800"
                            : "bg-gray-100 text-gray-600"
                        }`}
                      >
                        <span
                          className={`w-1.5 h-1.5 rounded-full ${
                            driver.status === "active"
                              ? "bg-green-500"
                              : "bg-gray-400"
                          }`}
                        />
                        {driver.status === "active" ? "Active" : "Inactive"}
                      </span>
                    </td>

                    {/* Joined Date */}
                    <td className="px-4 py-2 whitespace-nowrap">
                      <div className="text-sm text-gray-500">
                        {formatDate(driver.created_at)}
                      </div>
                    </td>

                    {/* Actions */}
                    <td className="px-4 py-2 whitespace-nowrap text-right">
                      <button
                        onClick={() => handleEdit(driver)}
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

export default DriverManagementPage;
