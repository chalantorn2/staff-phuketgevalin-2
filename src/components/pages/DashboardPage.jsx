// src/components/pages/DashboardPage.jsx - Practical Dashboard
import { useState, useEffect, useContext } from "react";
import { getCompanyClass } from "../../config/company";
import { backendApi } from "../../services/backendApi";
import { BookingContext } from "../../App";

function DashboardPage() {
  const { setSelectedBookingRef, setCurrentPage: setAppPage } =
    useContext(BookingContext);

  const [dashboardData, setDashboardData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [syncLoading, setSyncLoading] = useState(false);

  // Fetch practical dashboard data
  const fetchDashboardData = async () => {
    try {
      setLoading(true);
      const response = await backendApi.getPracticalDashboardData();

      if (response.success) {
        setDashboardData(response.data);
      }
    } catch (error) {
      console.error("Error fetching dashboard data:", error);
    } finally {
      setLoading(false);
    }
  };

  // Handle Sync & Refresh
  const handleSyncAndRefresh = async () => {
    try {
      setSyncLoading(true);
      const enhancedResponse = await backendApi.getEnhancedDashboardData(true);

      if (enhancedResponse.success) {
        // After sync, refresh dashboard data
        await fetchDashboardData();
      } else {
        alert(`Sync failed: ${enhancedResponse.error}`);
      }
    } catch (error) {
      console.error("Sync error:", error);
      alert(`Error: ${error.message}`);
    } finally {
      setSyncLoading(false);
    }
  };

  // Helper functions
  const formatDateTime = (dateString) => {
    if (!dateString) return "-";
    const date = new Date(dateString);
    return `${date.toLocaleDateString("en-GB")} ${date.toLocaleTimeString(
      "en-GB",
      { hour: "2-digit", minute: "2-digit" }
    )}`;
  };

  const formatDate = (dateString) => {
    if (!dateString || dateString === "0000-00-00 00:00:00") return "-";
    return new Date(dateString).toLocaleDateString("en-GB");
  };

  const getStatusBadge = (status) => {
    const statusMap = {
      PCON: { label: "Pending", color: "bg-cyan-100 text-cyan-800" },
      ACON: { label: "Confirmed", color: "bg-green-100 text-green-800" },
      PCAN: { label: "Cancel Req.", color: "bg-orange-100 text-orange-800" },
      ACAN: { label: "Cancelled", color: "bg-red-100 text-red-800" },
      PAMM: { label: "Amend Req.", color: "bg-yellow-100 text-yellow-800" },
      AAMM: { label: "Amended", color: "bg-purple-100 text-purple-800" },
    };
    const s = statusMap[status] || {
      label: status,
      color: "bg-gray-100 text-gray-800",
    };
    return (
      <span className={`px-2 py-1 text-xs font-medium rounded-full ${s.color}`}>
        {s.label}
      </span>
    );
  };

  const getUrgencyBadge = (urgency) => {
    const urgencyMap = {
      urgent: { label: "⚠️ URGENT", color: "bg-red-500 text-white" },
      today: { label: "Today", color: "bg-cyan-500 text-white" },
      normal: { label: "Normal", color: "bg-cyan-500 text-white" },
    };
    const u = urgencyMap[urgency] || urgencyMap.normal;
    return (
      <span className={`px-2 py-1 text-xs font-bold rounded ${u.color}`}>
        {u.label}
      </span>
    );
  };

  useEffect(() => {
    fetchDashboardData();
    // Auto refresh every 5 minutes
    const interval = setInterval(fetchDashboardData, 5 * 60 * 1000);
    return () => clearInterval(interval);
  }, []);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="text-center">
          <i className="fas fa-spinner fa-spin text-4xl text-cyan-500 mb-4"></i>
          <p className="text-gray-600">Loading dashboard...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">Dashboard</h1>
          <p className="text-gray-600 mt-1">ภาพรวมงานและข้อมูลสำคัญ</p>
        </div>
        <button
          onClick={() => setAppPage("test-sync")}
          className="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors text-sm font-medium"
        >
          <i className="fas fa-vial mr-2"></i>
          Test Sync
        </button>
      </div>

      {/* Quick Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {[
          {
            title: "Today's Pickups",
            count: dashboardData?.overview?.today_pickups || 0,
            icon: "fas fa-calendar-day",
            color: "cyan",
          },

          {
            title: "Active Tracking",
            count: dashboardData?.overview?.active_tracking || 0,
            icon: "fas fa-route",
            color: "cyan",
          },

          {
            title: "Completed Today",
            count: dashboardData?.overview?.completed_today || 0,
            icon: "fas fa-check-circle",
            color: "cyan",
          },
          {
            title: "Tomorrow's Pickups",
            count: dashboardData?.overview?.tomorrow_pickups || 0,
            icon: "fas fa-calendar-plus",
            color: "cyan",
          },
        ].map((stat, index) => (
          <div
            key={index}
            className={`bg-white rounded-xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow ${
              stat.clickable ? "cursor-pointer" : ""
            }`}
            onClick={stat.onClick}
          >
            <div className="flex items-center justify-between">
              <div className="flex-1">
                <p className="text-sm font-medium text-gray-600">
                  {stat.title}
                </p>
                <p className="text-2xl font-semibold text-gray-900 mt-1">
                  {stat.count}
                </p>
              </div>
              <div
                className={`w-12 h-12 rounded-lg flex items-center justify-center ${
                  stat.color === "cyan"
                    ? "bg-cyan-50"
                    : stat.color === "green"
                    ? "bg-green-50"
                    : stat.color === "orange"
                    ? "bg-orange-50"
                    : "bg-purple-50"
                }`}
              >
                <i
                  className={`${stat.icon} text-lg ${
                    stat.color === "cyan"
                      ? "text-cyan-600"
                      : stat.color === "green"
                      ? "text-green-600"
                      : stat.color === "orange"
                      ? "text-orange-600"
                      : "text-purple-600"
                  }`}
                ></i>
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Issues Alert */}
      {dashboardData?.issues?.total > 0 && (
        <div className="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
          <div className="flex items-start space-x-3">
            <i className="fas fa-exclamation-triangle text-yellow-600 text-xl mt-0.5"></i>
            <div className="flex-1">
              <h3 className="font-semibold text-yellow-900">
                ⚠️ Issues Found ({dashboardData.issues.total})
              </h3>
              <ul className="text-sm text-yellow-800 mt-2 space-y-1">
                {dashboardData.issues.missing_province > 0 && (
                  <li>
                    • {dashboardData.issues.missing_province} bookings missing
                    province
                  </li>
                )}
                {dashboardData.issues.missing_flight > 0 && (
                  <li>
                    • {dashboardData.issues.missing_flight} airport transfers
                    missing flight info
                  </li>
                )}
              </ul>
            </div>
          </div>
        </div>
      )}

      {/* Critical Jobs - Need Immediate Action */}
      {dashboardData?.critical_jobs?.length > 0 && (
        <div className="bg-white rounded-xl shadow-sm border border-red-200">
          <div className="px-6 py-4 bg-red-50 border-b border-red-200 rounded-t-xl">
            <div className="flex items-center justify-between">
              <div className="flex items-center space-x-2">
                <i className="fas fa-exclamation-circle text-red-600"></i>
                <h2 className="text-lg font-semibold text-red-900">
                  🚨 Critical Jobs - Need Assignment
                </h2>
                <span className="bg-red-600 text-white px-2 py-0.5 rounded-full text-xs font-bold">
                  {dashboardData.critical_jobs.length}
                </span>
              </div>
            </div>
          </div>

          <div className="p-6">
            <div className="overflow-x-auto">
              <table className="w-full text-nowrap">
                <thead>
                  <tr className="border-b border-gray-200">
                    <th className="text-left py-3 px-4 font-medium text-gray-700">
                      Urgency
                    </th>
                    <th className="text-left py-3 px-4 font-medium text-gray-700">
                      Booking Ref
                    </th>
                    <th className="text-left py-3 px-4 font-medium text-gray-700">
                      Pickup Time
                    </th>
                    <th className="text-left py-3 px-4 font-medium text-gray-700">
                      Passenger
                    </th>
                    <th className="text-left py-3 px-4 font-medium text-gray-700">
                      Province
                    </th>
                    <th className="text-left py-3 px-4 font-medium text-gray-700">
                      Resort
                    </th>
                    <th className="text-left py-3 px-4 font-medium text-gray-700">
                      Vehicle
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {dashboardData.critical_jobs.map((job, index) => (
                    <tr
                      key={index}
                      className={`border-b border-gray-100 hover:bg-gray-50 ${
                        job.urgency === "urgent" ? "bg-red-50" : ""
                      }`}
                    >
                      <td className="py-3 px-4">
                        {getUrgencyBadge(job.urgency)}
                      </td>
                      <td className="py-3 px-4">
                        <button
                          className="text-cyan-600 hover:text-cyan-800 font-medium hover:underline"
                          onClick={() => {
                            setSelectedBookingRef({
                              ref: job.ref,
                              fromPage: "dashboard",
                            });
                            setAppPage("booking-detail");
                          }}
                        >
                          {job.ref}
                        </button>
                      </td>
                      <td className="py-3 px-4 text-sm font-medium text-gray-900">
                        {formatDateTime(job.pickup_date)}
                      </td>
                      <td className="py-3 px-4 text-sm text-gray-600">
                        {job.passenger} ({job.pax} pax)
                      </td>
                      <td className="py-3 px-4 text-sm text-gray-600">
                        {job.province}
                      </td>
                      <td className="py-3 px-4 text-sm text-gray-600">
                        {job.resort}
                      </td>
                      <td className="py-3 px-4 text-sm text-gray-600">
                        {job.vehicle}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* Recent Upcoming Bookings */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-200">
        <div className="px-6 py-4 border-b border-gray-200">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <i className="fas fa-list-ul text-gray-400"></i>
              <h2 className="text-lg font-semibold text-gray-900">
                Recent Bookings
              </h2>
            </div>
            <div className="flex items-center space-x-4">
              <span className="text-sm text-gray-500">
                Last synced:{" "}
                {dashboardData?.last_sync
                  ? formatDateTime(dashboardData.last_sync)
                  : "-"}
              </span>
              <button
                onClick={handleSyncAndRefresh}
                disabled={syncLoading}
                className="text-cyan-600 cursor-pointer border p-1.5 rounded-sm hover:text-white hover:bg-cyan-600 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <i
                  className={`fas fa-sync-alt mr-1 ${
                    syncLoading ? "animate-spin" : ""
                  }`}
                ></i>
                Sync & Refresh
              </button>
            </div>
          </div>
        </div>

        <div className="p-6">
          {dashboardData?.recent_bookings?.length === 0 ? (
            <div className="text-center py-12">
              <i className="fas fa-inbox text-4xl text-gray-300 mb-4"></i>
              <p className="text-gray-500">No upcoming bookings</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-nowrap">
                <thead>
                  <tr className="border-b border-gray-200">
                    <th className="text-left py-3 px-4 font-medium text-gray-700">
                      Booking Ref
                    </th>
                    <th className="text-left py-3 px-4 font-medium text-gray-700">
                      Status
                    </th>
                    <th className="text-left py-3 px-4 font-medium text-gray-700">
                      Pickup Time
                    </th>
                    <th className="text-left py-3 px-4 font-medium text-gray-700">
                      Passenger
                    </th>
                    <th className="text-left py-3 px-4 font-medium text-gray-700">
                      Province
                    </th>
                    <th className="text-left py-3 px-4 font-medium text-gray-700">
                      Resort
                    </th>
                    <th className="text-left py-3 px-4 font-medium text-gray-700">
                      Synced At
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {dashboardData?.recent_bookings?.map((booking, index) => (
                    <tr
                      key={index}
                      className="border-b border-gray-100 hover:bg-gray-50"
                    >
                      <td className="py-3 px-4">
                        <button
                          className="text-cyan-600 hover:text-cyan-800 font-medium hover:underline"
                          onClick={() => {
                            setSelectedBookingRef({
                              ref: booking.ref,
                              fromPage: "dashboard",
                            });
                            setAppPage("booking-detail");
                          }}
                        >
                          {booking.ref}
                        </button>
                      </td>
                      <td className="py-3 px-4">
                        {getStatusBadge(booking.status)}
                      </td>
                      <td className="py-3 px-4 text-sm font-medium text-gray-900">
                        {formatDateTime(booking.pickup_date)}
                      </td>
                      <td className="py-3 px-4 text-sm text-gray-600">
                        {booking.passenger} ({booking.pax} pax)
                      </td>
                      <td className="py-3 px-4 text-sm text-gray-600">
                        {booking.province}
                      </td>
                      <td className="py-3 px-4 text-sm text-gray-600">
                        {booking.resort}
                      </td>
                      <td className="py-3 px-4 text-sm text-gray-600">
                        {formatDateTime(booking.synced_at)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>

              <div className="mt-4 text-center text-sm text-gray-500">
                Showing {dashboardData?.recent_bookings?.length || 0} bookings
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

export default DashboardPage;
