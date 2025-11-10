// src/components/pages/UserManagementPage.jsx - Updated with New API
import { useState, useEffect } from "react";
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

function UserManagementPage() {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editingUser, setEditingUser] = useState(null);
  const [formData, setFormData] = useState({
    username: "",
    password: "",
    full_name: "",
    role: "user",
    status: "active",
  });
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  useEffect(() => {
    fetchUsers();
  }, []);

  const fetchUsers = async () => {
    try {
      setLoading(true);
      const result = await apiCall("/users/manage.php");

      if (result.success) {
        setUsers(result.data);
      } else {
        setError(result.message || "Failed to fetch users");
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

    try {
      const endpoint = editingUser
        ? `/users/manage.php?id=${editingUser.id}`
        : `/users/manage.php`;

      const method = editingUser ? "PUT" : "POST";

      // Don't send password if editing and password is empty
      const submitData = { ...formData };
      if (editingUser && !submitData.password) {
        delete submitData.password;
      }

      const result = await apiCall(endpoint, {
        method,
        body: JSON.stringify(submitData),
      });

      if (result.success) {
        setSuccess(result.message || "Operation completed successfully");
        setShowForm(false);
        setEditingUser(null);
        resetForm();
        fetchUsers();
      } else {
        setError(result.message || "Operation failed");
      }
    } catch (err) {
      setError("Network error. Please try again.");
    }
  };

  const handleEdit = (user) => {
    setEditingUser(user);
    setFormData({
      username: user.username,
      password: "", // Don't show existing password
      full_name: user.full_name,
      role: user.role,
      status: user.status,
    });
    setShowForm(true);
  };

  const handleDelete = async (userId) => {
    if (!confirm("Are you sure you want to delete this user?")) return;

    try {
      const result = await apiCall(`/users/manage.php?id=${userId}`, {
        method: "DELETE",
      });

      if (result.success) {
        setSuccess("User deleted successfully");
        fetchUsers();
      } else {
        setError(result.message || "Failed to delete user");
      }
    } catch (err) {
      setError("Network error. Please try again.");
    }
  };

  const resetForm = () => {
    setFormData({
      username: "",
      password: "",
      full_name: "",
      role: "user",
      status: "active",
    });
    setEditingUser(null);
    setShowForm(false);
  };

  const formatDate = (dateString) => {
    if (!dateString) return "-";
    return new Date(dateString).toLocaleDateString("en-GB");
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900">
            User Management
          </h1>
          <p className="text-gray-600 mt-1">จัดการข้อมูลผู้ใช้งานระบบ</p>
        </div>
        <button
          onClick={() => setShowForm(true)}
          className={`px-4 py-2 text-sm font-medium rounded-lg text-white transition-colors ${getCompanyClass(
            "primary"
          )} ${getCompanyClass("primaryHover")}`}
        >
          <i className="fas fa-plus mr-2"></i>
          Add User
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
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <div className="flex items-center justify-between mb-6">
            <h2 className="text-lg font-semibold text-gray-900">
              {editingUser ? "Edit User" : "Add New User"}
            </h2>
            <button
              onClick={resetForm}
              className="text-gray-400 hover:text-gray-600"
            >
              <i className="fas fa-times text-xl"></i>
            </button>
          </div>

          <form
            onSubmit={handleSubmit}
            className="grid grid-cols-1 md:grid-cols-2 gap-4"
          >
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Username
              </label>
              <input
                type="text"
                required
                disabled={editingUser} // Can't change username when editing
                value={formData.username}
                onChange={(e) =>
                  setFormData({ ...formData, username: e.target.value })
                }
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500 disabled:bg-gray-100"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Password{" "}
                {editingUser && (
                  <span className="text-gray-500">
                    (leave blank to keep current)
                  </span>
                )}
              </label>
              <input
                type="password"
                required={!editingUser}
                value={formData.password}
                onChange={(e) =>
                  setFormData({ ...formData, password: e.target.value })
                }
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Full Name
              </label>
              <input
                type="text"
                required
                value={formData.full_name}
                onChange={(e) =>
                  setFormData({ ...formData, full_name: e.target.value })
                }
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Role
              </label>
              <select
                value={formData.role}
                onChange={(e) =>
                  setFormData({ ...formData, role: e.target.value })
                }
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
              >
                <option value="user">User</option>
                <option value="admin">Admin</option>
              </select>
            </div>

            {editingUser && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Status
                </label>
                <select
                  value={formData.status}
                  onChange={(e) =>
                    setFormData({ ...formData, status: e.target.value })
                  }
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500"
                >
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
            )}

            <div className="md:col-span-2 flex gap-3">
              <button
                type="submit"
                className={`px-4 py-2 text-sm font-medium rounded-lg text-white transition-colors ${getCompanyClass(
                  "primary"
                )} ${getCompanyClass("primaryHover")}`}
              >
                {editingUser ? "Update User" : "Create User"}
              </button>
              <button
                type="button"
                onClick={resetForm}
                className="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"
              >
                Cancel
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Users Table */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        {loading ? (
          <div className="text-center py-12">
            <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <i className="fas fa-spinner animate-spin text-2xl text-gray-400"></i>
            </div>
            <p className="text-gray-500 font-medium">Loading users...</p>
          </div>
        ) : users.length === 0 ? (
          <div className="text-center py-12">
            <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <i className="fas fa-users text-2xl text-gray-400"></i>
            </div>
            <p className="text-gray-500 font-medium">No users found</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="bg-gray-50 border-b border-gray-200">
                  <th className="text-left py-4 px-6 font-semibold text-gray-900 text-sm tracking-wide">
                    User
                  </th>
                  <th className="text-left py-4 px-6 font-semibold text-gray-900 text-sm tracking-wide">
                    Role & Status
                  </th>
                  <th className="text-left py-4 px-6 font-semibold text-gray-900 text-sm tracking-wide">
                    Last Activity
                  </th>
                  <th className="text-center py-4 px-6 font-semibold text-gray-900 text-sm tracking-wide">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {users.map((user, index) => (
                  <tr
                    key={user.id}
                    className="hover:bg-gray-50 transition-colors duration-150"
                  >
                    {/* User Info */}
                    <td className="py-4 px-6">
                      <div className="flex items-center space-x-3">
                        <div className="w-10 h-10 bg-gradient-to-r from-cyan-500 to-cyan-600 rounded-full flex items-center justify-center text-white font-medium">
                          {user.full_name?.charAt(0)?.toUpperCase() ||
                            user.username?.charAt(0)?.toUpperCase() ||
                            "?"}
                        </div>
                        <div>
                          <div className="font-medium text-gray-900">
                            {user.full_name}
                          </div>
                          <div className="text-sm text-gray-500">
                            @{user.username}
                          </div>
                        </div>
                      </div>
                    </td>

                    {/* Role & Status */}
                    <td className="py-4 px-6">
                      <div className="flex items-center space-x-2">
                        <span
                          className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                            user.role === "admin"
                              ? "bg-cyan-100 text-cyan-800"
                              : "bg-gray-100 text-gray-800"
                          }`}
                        >
                          {user.role === "admin" ? "Admin" : "User"}
                        </span>
                        <span
                          className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                            user.status === "active"
                              ? "bg-green-100 text-green-800"
                              : "bg-gray-100 text-gray-600"
                          }`}
                        >
                          <span
                            className={`w-1.5 h-1.5 rounded-full mr-1.5 ${
                              user.status === "active"
                                ? "bg-green-400"
                                : "bg-gray-400"
                            }`}
                          ></span>
                          {user.status === "active" ? "Active" : "Inactive"}
                        </span>
                      </div>
                    </td>

                    {/* Last Activity */}
                    <td className="py-4 px-6">
                      <div className="text-sm text-gray-900">
                        {user.last_login ? (
                          <>
                            <div>Login: {formatDate(user.last_login)}</div>
                            <div className="text-xs text-gray-500">
                              Created: {formatDate(user.created_at)}
                            </div>
                          </>
                        ) : (
                          <>
                            <div className="text-gray-500">Never logged in</div>
                            <div className="text-xs text-gray-500">
                              Created: {formatDate(user.created_at)}
                            </div>
                          </>
                        )}
                      </div>
                    </td>

                    {/* Actions */}
                    <td className="py-4 px-6">
                      <div className="flex items-center justify-center space-x-3">
                        <button
                          onClick={() => handleEdit(user)}
                          className="inline-flex items-center px-3 py-1.5 border border-cyan-300 text-xs font-medium rounded-md text-cyan-700 bg-cyan-50 hover:bg-cyan-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-500 transition-colors"
                          title="Edit user"
                        >
                          <i className="fas fa-edit mr-1"></i>
                          Edit
                        </button>
                        <button
                          onClick={() => handleDelete(user.id)}
                          className="inline-flex items-center px-3 py-1.5 border border-red-300 text-xs font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors"
                          title="Delete user"
                        >
                          <i className="fas fa-trash mr-1"></i>
                          Delete
                        </button>
                      </div>
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

export default UserManagementPage;
