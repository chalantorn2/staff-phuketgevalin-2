// src/components/modals/ChangeTrackingStatusModal.jsx
import React, { useState } from "react";

function ChangeTrackingStatusModal({ isOpen, onClose, assignment, onSuccess }) {
  const [loading, setLoading] = useState(false);
  const [selectedStatus, setSelectedStatus] = useState(null);

  if (!isOpen || !assignment) return null;

  const handleChangeStatus = async (status) => {
    if (!status) return;

    // Show confirmation
    const statusText = status === "COMPLETED" ? "เสร็จสิ้น" : "No Show";
    const confirmMessage = `คุณต้องการเปลี่ยนสถานะเป็น "${statusText}" ใช่หรือไม่?`;

    if (!confirm(confirmMessage)) {
      return;
    }

    setLoading(true);
    setSelectedStatus(status);

    try {
      // Check if tracking token exists
      if (!assignment.tracking.token) {
        alert("ไม่พบ tracking token สำหรับงานนี้");
        setLoading(false);
        return;
      }

      const response = await fetch("/api/tracking/complete.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          token: assignment.tracking.token,
          status: status,
          notes: `Status changed by staff to ${status}`,
        }),
      });

      const data = await response.json();

      if (data.success) {
        alert(`✅ เปลี่ยนสถานะเป็น "${statusText}" สำเร็จ`);
        if (onSuccess) {
          onSuccess(data);
        }
        onClose();
      } else {
        throw new Error(data.error || "Failed to update status");
      }
    } catch (error) {
      console.error("Error changing status:", error);
      alert(`❌ เกิดข้อผิดพลาด: ${error.message}`);
    } finally {
      setLoading(false);
      setSelectedStatus(null);
    }
  };

  // Check if assignment already completed
  const isCompleted = assignment.tracking.status === "completed";
  const currentCompletionType = assignment.completion_type;

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
          maxHeight: "90vh",
          overflow: "auto",
        }}
        onClick={(e) => e.stopPropagation()}
      >
        {/* Modal Header */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
          <h3 className="text-lg font-semibold text-gray-900">
            <i className="fas fa-exchange-alt text-cyan-600 mr-2"></i>
            เปลี่ยนสถานะงาน
          </h3>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600"
          >
            <i className="fas fa-times text-xl"></i>
          </button>
        </div>

        {/* Modal Body */}
        <div className="p-6">
          {/* Assignment Info */}
          <div className="bg-gray-50 rounded-lg p-4 mb-6">
            <div className="space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-gray-600">Booking Ref:</span>
                <span className="font-semibold text-gray-900">
                  {assignment.booking_ref}
                </span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-600">คนขับ:</span>
                <span className="font-semibold text-gray-900">
                  {assignment.driver.name}
                </span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-600">สถานะปัจจุบัน:</span>
                <span className="font-semibold text-gray-900">
                  {isCompleted
                    ? currentCompletionType === "NO_SHOW"
                      ? "No Show"
                      : "เสร็จสิ้น"
                    : assignment.tracking.status === "active"
                    ? "กำลังดำเนินการ"
                    : "ยังไม่เริ่ม"}
                </span>
              </div>
            </div>
          </div>

          {/* Status already completed - show info */}
          {isCompleted && (
            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
              <div className="flex items-start gap-3">
                <i className="fas fa-info-circle text-yellow-600 mt-0.5"></i>
                <div className="flex-1 text-sm text-yellow-800">
                  <p className="font-medium mb-1">งานนี้เสร็จสิ้นแล้ว</p>
                  <p className="text-xs">
                    สถานะ:{" "}
                    {currentCompletionType === "NO_SHOW"
                      ? "No Show"
                      : "เสร็จสิ้น"}
                  </p>
                  {assignment.tracking.completed_at && (
                    <p className="text-xs mt-1">
                      เวลา:{" "}
                      {new Date(
                        assignment.tracking.completed_at
                      ).toLocaleString("th-TH")}
                    </p>
                  )}
                </div>
              </div>
            </div>
          )}

          {/* No tracking token warning */}
          {!assignment.tracking.token && (
            <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
              <div className="flex items-start gap-3">
                <i className="fas fa-exclamation-triangle text-red-600 mt-0.5"></i>
                <div className="flex-1 text-sm text-red-800">
                  <p className="font-medium">ไม่สามารถเปลี่ยนสถานะได้</p>
                  <p className="text-xs mt-1">
                    งานนี้ยังไม่มี tracking token กรุณาสร้าง tracking link ก่อน
                  </p>
                </div>
              </div>
            </div>
          )}

          {/* Status Selection Buttons */}
          {assignment.tracking.token && (
            <div className="space-y-3">
              <p className="text-sm font-medium text-gray-700 mb-3">
                เลือกสถานะใหม่:
              </p>

              {/* Completed Button */}
              <button
                onClick={() => handleChangeStatus("COMPLETED")}
                disabled={loading}
                className="w-full flex items-center justify-between px-4 py-4 border-2 border-cyan-200 rounded-lg hover:border-cyan-400 hover:bg-cyan-50 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-cyan-100 rounded-full flex items-center justify-center">
                    <i className="fas fa-check-circle text-cyan-600 text-lg"></i>
                  </div>
                  <div className="text-left">
                    <p className="font-semibold text-gray-900">
                      เสร็จสิ้น (Completed)
                    </p>
                    <p className="text-xs text-gray-600">
                      งานเสร็จสมบูรณ์ ส่งผู้โดยสารถึงจุดหมายแล้ว
                    </p>
                  </div>
                </div>
                {loading && selectedStatus === "COMPLETED" && (
                  <i className="fas fa-spinner fa-spin text-cyan-600"></i>
                )}
              </button>

              {/* No Show Button */}
              <button
                onClick={() => handleChangeStatus("NO_SHOW")}
                disabled={loading}
                className="w-full flex items-center justify-between px-4 py-4 border-2 border-red-200 rounded-lg hover:border-red-400 hover:bg-red-50 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                    <i className="fas fa-user-slash text-red-600 text-lg"></i>
                  </div>
                  <div className="text-left">
                    <p className="font-semibold text-gray-900">No Show</p>
                    <p className="text-xs text-gray-600">
                      ผู้โดยสารไม่มาตามนัด
                    </p>
                  </div>
                </div>
                {loading && selectedStatus === "NO_SHOW" && (
                  <i className="fas fa-spinner fa-spin text-red-600"></i>
                )}
              </button>
            </div>
          )}
        </div>

        {/* Modal Footer */}
        <div className="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
          <button
            onClick={onClose}
            disabled={loading}
            className="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-white disabled:opacity-50 disabled:cursor-not-allowed"
          >
            ยกเลิก
          </button>
        </div>
      </div>
    </div>
  );
}

export default ChangeTrackingStatusModal;
