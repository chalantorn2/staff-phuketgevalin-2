// src/components/modals/AddDriverModal.jsx
import React, { useState } from "react";
import { UserPlus, X } from "lucide-react";

function AddDriverModal({ isOpen, onClose, onSuccess }) {
  const [formData, setFormData] = useState({
    name: "",
    phone_number: "",
    license_number: "",
    code: "",
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleChange = (e) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value,
    });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      // Validate required fields
      if (!formData.name || !formData.phone_number) {
        throw new Error("กรุณากรอกชื่อและเบอร์โทรศัพท์");
      }

      // Format and validate phone number
      let phoneNumber = formData.phone_number.trim().replace(/\s/g, ""); // Remove spaces

      // Convert to +66 format
      if (phoneNumber.startsWith("0")) {
        phoneNumber = "+66" + phoneNumber.substring(1);
      } else if (!phoneNumber.startsWith("+66")) {
        phoneNumber = "+66" + phoneNumber;
      }

      // Validate Thai mobile format: +66[6-9]xxxxxxxx
      const phoneRegex = /^\+66[6-9]\d{8}$/;
      if (!phoneRegex.test(phoneNumber)) {
        throw new Error("เบอร์โทรศัพท์ไม่ถูกต้อง");
      }

      const response = await fetch("/api/drivers/manage.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          name: formData.name,
          phone_number: phoneNumber,
          license_number: formData.license_number || "",
          code: formData.code || "",
          preferred_contact_method: "VOICE",
          contact_methods: ["VOICE"],
        }),
      });

      const data = await response.json();

      if (!data.success) {
        throw new Error(data.message || "เพิ่มคนขับไม่สำเร็จ");
      }

      // Success
      onSuccess && onSuccess(data.data);
      onClose();
      // Reset form
      setFormData({
        name: "",
        phone_number: "",
        license_number: "",
        code: "",
      });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  if (!isOpen) return null;

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
        zIndex: 10000,
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
            <UserPlus size={20} className="text-green-600" />
            เพิ่มคนขับใหม่
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
          {/* Name */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              ชื่อ-นามสกุล <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              name="name"
              value={formData.name}
              onChange={handleChange}
              required
              placeholder="เช่น: สมชาย ใจดี"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
            />
          </div>

          {/* Phone */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              เบอร์โทรศัพท์ <span className="text-red-500">*</span>
            </label>
            <input
              type="tel"
              name="phone_number"
              value={formData.phone_number}
              onChange={handleChange}
              required
              placeholder="0812345678"
              maxLength={12}
              pattern="[0-9+]*"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
            />
            <p className="text-xs text-gray-500 mt-1">
              10 หลัก ขึ้นต้น 06/08/09
            </p>
          </div>

          {/* Code */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              รหัสคนขับ (Code)
            </label>
            <input
              type="text"
              name="code"
              value={formData.code}
              onChange={handleChange}
              placeholder="เช่น: DRV001"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
            />
          </div>

          {/* License Number */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              เลขใบขับขี่ (ถ้ามี)
            </label>
            <input
              type="text"
              name="license_number"
              value={formData.license_number}
              onChange={handleChange}
              placeholder="เช่น: 12345678"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
            />
          </div>

          {/* Error Message */}
          {error && (
            <div className="bg-red-50 border border-red-200 rounded-lg p-3">
              <p className="text-sm text-red-800">{error}</p>
            </div>
          )}
        </form>

        {/* Footer */}
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
            className="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            disabled={loading}
          >
            {loading ? (
              <>
                <i className="fas fa-spinner fa-spin"></i>
                กำลังเพิ่ม...
              </>
            ) : (
              <>
                <UserPlus size={16} />
                เพิ่มคนขับ
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
}

export default AddDriverModal;
