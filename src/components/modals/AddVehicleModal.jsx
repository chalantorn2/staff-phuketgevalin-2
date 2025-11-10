// src/components/modals/AddVehicleModal.jsx
import React, { useState } from "react";
import { CarFront, X } from "lucide-react";

function AddVehicleModal({ isOpen, onClose, onSuccess }) {
  const [formData, setFormData] = useState({
    registration: "",
    brand: "",
    model: "",
    color: "",
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
      if (!formData.registration) {
        throw new Error("กรุณากรอกทะเบียนรถ");
      }

      const response = await fetch("/api/vehicles/manage.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          registration: formData.registration.trim(),
          brand: formData.brand || "",
          model: formData.model || "",
          color: formData.color || "",
          description: "",
          default_driver_id: null,
        }),
      });

      const data = await response.json();

      if (!data.success) {
        throw new Error(data.message || "เพิ่มรถไม่สำเร็จ");
      }

      // Success
      onSuccess && onSuccess(data.data);
      onClose();
      // Reset form
      setFormData({
        registration: "",
        brand: "",
        model: "",
        color: "",
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
            <CarFront size={20} className="text-green-600" />
            เพิ่มรถใหม่
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
          {/* Registration */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              ทะเบียนรถ <span className="text-red-500">*</span>
            </label>
            <input
              type="text"
              name="registration"
              value={formData.registration}
              onChange={handleChange}
              required
              placeholder="เช่น: กก 1234 กรุงเทพ"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 uppercase"
            />
            <p className="text-xs text-gray-500 mt-1">
              ไม่ควรใส่ตัวอักษรภาษาไทย
            </p>
          </div>

          {/* Brand */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              ยี่ห้อ (ถ้ามี)
            </label>
            <input
              type="text"
              name="brand"
              value={formData.brand}
              onChange={handleChange}
              placeholder="เช่น: Toyota, Honda, Isuzu"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
            />
          </div>

          {/* Model */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              รุ่น (ถ้ามี)
            </label>
            <input
              type="text"
              name="model"
              value={formData.model}
              onChange={handleChange}
              placeholder="เช่น: Fortuner, CR-V, D-MAX"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
            />
          </div>

          {/* Color */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              สี (ถ้ามี)
            </label>
            <input
              type="text"
              name="color"
              value={formData.color}
              onChange={handleChange}
              placeholder="เช่น: ขาว, ดำ, เทา"
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
                <CarFront size={16} />
                เพิ่มรถ
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
}

export default AddVehicleModal;
