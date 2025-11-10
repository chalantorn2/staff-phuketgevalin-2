// src/components/pages/DriverTrackingPage.jsx
import { useState, useEffect, useRef } from "react";
import { useParams } from "react-router-dom";

function DriverTrackingPage() {
  const { token } = useParams();
  const [trackingInfo, setTrackingInfo] = useState(null);
  const [status, setStatus] = useState("idle"); // idle, tracking, completed
  const [currentLocation, setCurrentLocation] = useState(null);
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(true);
  const intervalRef = useRef(null);

  useEffect(() => {
    if (token) {
      fetchTrackingInfo();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token]);

  useEffect(() => {
    // Cleanup interval on unmount
    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
      }
    };
  }, []);

  const fetchTrackingInfo = async () => {
    try {
      setLoading(true);
      const response = await fetch(`/api/tracking/info.php?token=${token}`);
      const data = await response.json();

      if (data.success) {
        setTrackingInfo(data.data);

        // If already active, start tracking
        if (data.data.status === "active") {
          setStatus("tracking");
          startTracking(data.data.tracking.interval);
        } else if (data.data.status === "completed") {
          setStatus("completed");
        }
      } else {
        setError(data.error || "ลิงก์ไม่ถูกต้องหรือหมดอายุแล้ว");
      }
    } catch (err) {
      console.error("Error fetching tracking info:", err);
      setError("ไม่สามารถโหลดข้อมูลได้ กรุณาลองใหม่อีกครั้ง");
    } finally {
      setLoading(false);
    }
  };

  const startJob = async () => {
    if (!navigator.geolocation) {
      alert("อุปกรณ์นี้ไม่รองรับ GPS");
      return;
    }

    try {
      // Request location permission and get initial location
      navigator.geolocation.getCurrentPosition(
        async (position) => {
          // Start job on backend
          const response = await fetch("/api/tracking/start.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ token }),
          });

          const data = await response.json();

          if (data.success) {
            setStatus("tracking");
            setTrackingInfo((prev) => ({
              ...prev,
              status: "active",
              tracking: {
                ...prev.tracking,
                started_at: data.data.started_at,
              },
            }));

            // Send initial location
            await sendLocation(position);

            // Start interval
            startTracking(trackingInfo?.tracking?.interval || 30);

            alert("✅ เริ่มงานสำเร็จ! GPS กำลังส่งตำแหน่ง");
          } else {
            alert("❌ ไม่สามารถเริ่มงานได้: " + data.error);
          }
        },
        (error) => {
          console.error("GPS Error:", error);
          alert("❌ ไม่สามารถเข้าถึง GPS ได้ กรุณาอนุญาตการเข้าถึงตำแหน่ง");
        },
        {
          enableHighAccuracy: true,
          timeout: 10000,
          maximumAge: 0,
        }
      );
    } catch (err) {
      console.error("Start job error:", err);
      alert("❌ ไม่สามารถเริ่มงานได้");
    }
  };

  const startTracking = (interval = 30) => {
    // Send location immediately
    sendLocationUpdate();

    // Then send every N seconds
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
    }

    intervalRef.current = setInterval(() => {
      sendLocationUpdate();
    }, interval * 1000);
  };

  const sendLocationUpdate = () => {
    navigator.geolocation.getCurrentPosition(
      async (position) => {
        await sendLocation(position);
      },
      (error) => {
        console.error("GPS Error:", error);
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0,
      }
    );
  };

  const sendLocation = async (position) => {
    const location = {
      latitude: position.coords.latitude,
      longitude: position.coords.longitude,
      accuracy: position.coords.accuracy,
      status: "BEFORE_PICKUP", // TODO: Allow driver to change status
    };

    setCurrentLocation(location);

    try {
      const response = await fetch("/api/tracking/location.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ token, ...location }),
      });

      const data = await response.json();

      if (!data.success) {
        console.error("Failed to send location:", data.error);
      }
    } catch (err) {
      console.error("Error sending location:", err);
    }
  };

  const completeJob = async () => {
    if (!confirm("ต้องการจบงานใช่หรือไม่?")) return;

    try {
      // Stop tracking
      if (intervalRef.current) {
        clearInterval(intervalRef.current);
      }

      // Get final location
      navigator.geolocation.getCurrentPosition(
        async (position) => {
          // Complete on backend with final location
          const response = await fetch("/api/tracking/complete.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              token,
              status: "COMPLETED",
              latitude: position.coords.latitude,
              longitude: position.coords.longitude,
              notes: "Completed by driver",
            }),
          });

          const data = await response.json();

          if (data.success) {
            setStatus("completed");
            setTrackingInfo((prev) => ({
              ...prev,
              status: "completed",
              tracking: {
                ...prev.tracking,
                completed_at: data.data.completed_at,
              },
            }));
            alert("✅ งานเสร็จสมบูรณ์!");
          } else {
            alert("❌ ไม่สามารถจบงานได้: " + data.error);
          }
        },
        () => {
          // If can't get location, complete anyway
          completeWithoutLocation();
        }
      );
    } catch (err) {
      console.error("Complete job error:", err);
      alert("❌ ไม่สามารถจบงานได้");
    }
  };

  const completeWithoutLocation = async () => {
    try {
      const response = await fetch("/api/tracking/complete.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          token,
          status: "COMPLETED",
          notes: "Completed by driver (no final location)",
        }),
      });

      const data = await response.json();

      if (data.success) {
        setStatus("completed");
        alert("✅ งานเสร็จสมบูรณ์!");
      }
    } catch (err) {
      console.error("Complete without location error:", err);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div className="text-center">
          <i className="fas fa-spinner fa-spin text-4xl text-cyan-600 mb-4"></i>
          <p className="text-gray-600">กำลังโหลด...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-red-50 flex items-center justify-center p-4">
        <div className="bg-white rounded-xl shadow-lg p-6 max-w-md w-full">
          <div className="text-red-600 text-center">
            <i className="fas fa-exclamation-triangle text-4xl mb-4"></i>
            <h2 className="text-xl font-semibold mb-2">ลิงก์ไม่ถูกต้อง</h2>
            <p className="text-gray-700">{error}</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-cyan-50 to-cyan-50 p-4">
      <div className="max-w-md mx-auto space-y-4 py-4">
        {/* Header Card */}
        <div className="bg-white rounded-xl shadow-lg p-6">
          <div className="flex items-center justify-between mb-4">
            <h1 className="text-2xl font-bold text-gray-900">🚗 Transfer</h1>
            <span
              className={`px-3 py-1 rounded-full text-sm font-medium ${
                status === "idle"
                  ? "bg-yellow-100 text-yellow-800"
                  : status === "tracking"
                  ? "bg-green-100 text-green-800 animate-pulse"
                  : "bg-cyan-100 text-cyan-800"
              }`}
            >
              {status === "idle"
                ? "รอเริ่มงาน"
                : status === "tracking"
                ? "🔴 กำลังทำงาน"
                : "✅ เสร็จสิ้น"}
            </span>
          </div>

          <div className="space-y-2 text-sm">
            <p className="text-gray-500">Booking Ref:</p>
            <p className="text-lg font-semibold text-cyan-600">
              {trackingInfo?.booking_ref}
            </p>
          </div>
        </div>

        {/* Booking Info Card */}
        <div className="bg-white rounded-xl shadow-lg p-6">
          <h2 className="text-lg font-semibold mb-4 text-gray-900">
            📋 รายละเอียดการจอง
          </h2>

          <div className="space-y-3">
            <div className="flex items-start gap-3">
              <i className="fas fa-user text-cyan-600 mt-1"></i>
              <div>
                <p className="text-sm text-gray-500">ผู้โดยสาร</p>
                <p className="font-medium">
                  {trackingInfo?.booking?.passenger_name}
                </p>
                <p className="text-sm text-gray-600">
                  {trackingInfo?.booking?.passenger_phone}
                </p>
              </div>
            </div>

            <div className="flex items-start gap-3">
              <i className="fas fa-map-marker-alt text-green-600 mt-1"></i>
              <div className="flex-1">
                <p className="text-sm text-gray-500">จุดรับ</p>
                <p className="font-medium">
                  {trackingInfo?.booking?.pickup_location}
                </p>
                <p className="text-sm text-cyan-600">
                  {trackingInfo?.booking?.pickup_datetime}
                </p>
              </div>
            </div>

            <div className="flex items-start gap-3">
              <i className="fas fa-map-marker-alt text-red-600 mt-1"></i>
              <div>
                <p className="text-sm text-gray-500">จุดส่ง</p>
                <p className="font-medium">
                  {trackingInfo?.booking?.dropoff_location}
                </p>
              </div>
            </div>

            <div className="flex items-center gap-3">
              <i className="fas fa-users text-purple-600"></i>
              <div>
                <p className="text-sm text-gray-500">จำนวนผู้โดยสาร</p>
                <p className="font-medium">{trackingInfo?.booking?.pax} คน</p>
              </div>
            </div>
          </div>
        </div>

        {/* GPS Status Card (show when tracking) */}
        {status === "tracking" && currentLocation && (
          <div className="bg-green-50 border-2 border-green-200 rounded-xl p-4">
            <div className="flex items-center gap-3 mb-2">
              <i className="fas fa-satellite-dish text-green-600 text-xl"></i>
              <h3 className="font-semibold text-green-900">GPS กำลังทำงาน</h3>
            </div>
            <div className="text-sm text-green-800 space-y-1">
              <p>📍 Lat: {currentLocation.latitude.toFixed(6)}</p>
              <p>📍 Lng: {currentLocation.longitude.toFixed(6)}</p>
              <p>🎯 ความแม่นยำ: {currentLocation.accuracy?.toFixed(0)} เมตร</p>
              <p className="text-xs text-green-600 mt-2">
                ส่งตำแหน่งทุก {trackingInfo?.tracking?.interval} วินาที
              </p>
            </div>
          </div>
        )}

        {/* Action Buttons */}
        <div className="space-y-3">
          {status === "idle" && (
            <button
              onClick={startJob}
              className="w-full bg-gradient-to-r from-green-500 to-green-600 text-white py-4 rounded-xl font-semibold text-lg shadow-lg hover:from-green-600 hover:to-green-700 transition-all"
            >
              <i className="fas fa-play-circle mr-2"></i>
              เริ่มงาน
            </button>
          )}

          {status === "tracking" && (
            <button
              onClick={completeJob}
              className="w-full bg-gradient-to-r from-cyan-500 to-cyan-600 text-white py-4 rounded-xl font-semibold text-lg shadow-lg hover:from-cyan-600 hover:to-cyan-700 transition-all"
            >
              <i className="fas fa-check-circle mr-2"></i>
              เสร็จสิ้นงาน
            </button>
          )}

          {status === "completed" && (
            <div className="bg-cyan-50 border-2 border-cyan-200 rounded-xl p-6 text-center">
              <i className="fas fa-check-circle text-cyan-600 text-5xl mb-3"></i>
              <h3 className="text-xl font-semibold text-cyan-900 mb-2">
                งานเสร็จสมบูรณ์!
              </h3>
              <p className="text-cyan-700">ขอบคุณสำหรับการให้บริการ</p>
            </div>
          )}
        </div>

        {/* Footer Info */}
        <div className="text-center text-sm text-gray-500 pt-4">
          <p>Phuket Gevalin</p>
          <p className="text-xs">
            ลิงก์นี้จะหมดอายุ:{" "}
            {trackingInfo?.expires_at
              ? new Date(trackingInfo.expires_at).toLocaleString("th-TH")
              : "-"}
          </p>
        </div>
      </div>
    </div>
  );
}

export default DriverTrackingPage;
