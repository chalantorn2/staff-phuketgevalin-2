// src/App.jsx - Complete App with Login + Original Features
import { useState, useEffect, createContext } from "react";
import MainLayout from "./components/layout/MainLayout";
import LoginPage from "./components/pages/LoginPage";
import DashboardPage from "./components/pages/DashboardPage";
import BookingManagementPage from "./components/pages/BookingManagementPage";
import DriverManagementPage from "./components/pages/DriverManagementPage";
import JobAssignmentsPage from "./components/pages/JobAssignmentsPage";
import BookingOverviewPage from "./components/pages/BookingOverviewPage";
import UserManagementPage from "./components/pages/UserManagementPage";
import BookingDetailPage from "./components/pages/BookingDetailPage";
import VehicleManagementPage from "./components/pages/VehicleManagementPage";
import TestSyncPage from "./components/pages/TestSyncPage";
import "./App.css";

export const BookingContext = createContext();
export const NavigationContext = createContext();

// API Client with Proxy
const API_BASE = "/api"; // ใช้ Vite proxy

const apiCall = async (endpoint, options = {}) => {
  const url = `${API_BASE}${endpoint}`;
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

function App() {
  // Auth state
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  // Navigation state - load from localStorage or default to dashboard
  const [currentPage, setCurrentPage] = useState(() => {
    return localStorage.getItem("currentPage") || "dashboard";
  });
  const [selectedBookingRef, setSelectedBookingRef] = useState(() => {
    const saved = localStorage.getItem("selectedBookingRef");
    return saved ? JSON.parse(saved) : null;
  });

  // Save current page to localStorage when it changes
  useEffect(() => {
    localStorage.setItem("currentPage", currentPage);
  }, [currentPage]);

  // Save selected booking ref to localStorage when it changes
  useEffect(() => {
    if (selectedBookingRef) {
      localStorage.setItem(
        "selectedBookingRef",
        JSON.stringify(selectedBookingRef)
      );
    } else {
      localStorage.removeItem("selectedBookingRef");
    }
  }, [selectedBookingRef]);

  // Check for saved user on app start
  useEffect(() => {
    const savedUser = localStorage.getItem("user");
    if (savedUser) {
      setUser(JSON.parse(savedUser));
    }
    setLoading(false);
  }, []);

  // Handle URL hash navigation on mount and hash change
  useEffect(() => {
    const handleHashChange = () => {
      const hash = window.location.hash;

      // Parse hash: #booking/HBEDS-26581356?from=assignments
      if (hash.startsWith('#booking/')) {
        const hashWithoutPrefix = hash.replace('#booking/', '');
        const [bookingRef, queryString] = hashWithoutPrefix.split('?');

        // Parse query parameters
        const params = new URLSearchParams(queryString);
        const fromPage = params.get('from') || 'jobs'; // Default to jobs if not specified

        if (bookingRef) {
          setSelectedBookingRef({
            ref: bookingRef,
            fromPage: fromPage,
          });
          setCurrentPage("booking-detail");
        }
      }
    };

    // Handle initial hash on load
    handleHashChange();

    // Listen for hash changes
    window.addEventListener('hashchange', handleHashChange);

    return () => {
      window.removeEventListener('hashchange', handleHashChange);
    };
  }, []);

  // Custom setCurrentPage that saves to localStorage
  const handleSetCurrentPage = (pageId) => {
    setCurrentPage(pageId);
    localStorage.setItem("currentPage", pageId);
  };

  const handleLogin = async (formData) => {
    const result = await apiCall("/auth/login.php", {
      method: "POST",
      body: JSON.stringify(formData),
    });

    if (result.success) {
      localStorage.setItem("user", JSON.stringify(result.data.user));
      localStorage.setItem("token", result.data.token);
      setUser(result.data.user);
      return { success: true };
    } else {
      return { success: false, error: result.message || "Login failed" };
    }
  };

  const handleLogout = async () => {
    try {
      // Call logout API if exists
      await apiCall("/auth/logout.php", { method: "POST" });
    } catch (error) {
      // Continue with logout even if API fails
    }

    // Clear all saved data
    localStorage.removeItem("user");
    localStorage.removeItem("token");
    localStorage.removeItem("currentPage");
    localStorage.removeItem("selectedBookingRef");

    setUser(null);
    setCurrentPage("dashboard");
  };

  const renderCurrentPage = () => {
    switch (currentPage) {
      case "dashboard":
        return <DashboardPage />;
      case "jobs":
        return <BookingManagementPage />;
      case "drivers":
        return <DriverManagementPage />;
      case "vehicles":
        return <VehicleManagementPage />;
      case "assignments":
        return <JobAssignmentsPage />;
      case "booking":
        return <BookingOverviewPage />;
      case "usermanagement":
        // Only show for admin users
        return user?.role === "admin" ? (
          <UserManagementPage />
        ) : (
          <DashboardPage />
        );
      case "booking-detail":
        return (
          <BookingDetailPage
            bookingRef={selectedBookingRef}
            onBack={() =>
              handleSetCurrentPage(selectedBookingRef?.fromPage || "dashboard")
            }
            fromPage={selectedBookingRef?.fromPage || "dashboard"}
          />
        );
      case "test-sync":
        return <TestSyncPage />;
      default:
        return <DashboardPage />;
    }
  };

  // Show loading while checking auth
  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i className="fas fa-spinner animate-spin text-2xl text-gray-400"></i>
          </div>
          <p className="text-gray-500 font-medium">Loading...</p>
        </div>
      </div>
    );
  }

  // Show login page if not authenticated
  if (!user) {
    return <LoginPage onLogin={handleLogin} />;
  }

  // Show main app if authenticated
  return (
    <MainLayout
      currentPage={currentPage}
      setCurrentPage={handleSetCurrentPage}
      user={user}
      onLogout={handleLogout}
    >
      <NavigationContext.Provider value={{ handleSetCurrentPage }}>
        <BookingContext.Provider
          value={{
            setSelectedBookingRef,
            setCurrentPage: handleSetCurrentPage,
            refreshBookings: () => {
              window.dispatchEvent(new Event("refreshBookings"));
            },
          }}
        >
          {renderCurrentPage()}
        </BookingContext.Provider>
      </NavigationContext.Provider>
    </MainLayout>
  );
}

export default App;
