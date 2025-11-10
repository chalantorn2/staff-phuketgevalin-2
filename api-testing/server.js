import { serve } from "bun";
import { sendApiRequest } from "./api.js";

serve({
  port: 3000,
  async fetch(req) {
    const url = new URL(req.url);

    if (url.pathname === "/" || url.pathname === "/index.html") {
      return new Response(Bun.file("index.html"));
    }
    if (url.pathname === "/batch-test.html") {
      return new Response(Bun.file("batch-test.html"));
    }

    if (url.pathname.startsWith("/api")) {
      const requestBody = await req.json();
      let result;

      const {
        bookingRef,
        vehicleIdentifier,
        dateFrom,
        dateTo,
        pageNumber,
        startAt,
        apiKey,
        baseUrl,
        apiVersion,
      } = requestBody;
      if (!apiKey || !baseUrl || !apiVersion) {
        return new Response(
          JSON.stringify({
            error:
              "Missing required parameters: apiKey, baseUrl, or apiVersion",
          }),
          {
            status: 400,
            headers: { "Content-Type": "application/json" },
          }
        );
      }

      if (url.pathname === "/api/retrieve-booking") {
        if (!bookingRef) {
          return new Response(JSON.stringify({ error: "Missing bookingRef" }), {
            status: 400,
            headers: { "Content-Type": "application/json" },
          });
        }
        result = await sendApiRequest(
          `${baseUrl}/bookings/${bookingRef}`,
          "GET",
          apiKey,
          apiVersion
        );
      }

      if (url.pathname === "/api/search-last-action") {
        if (!dateFrom || !dateTo || !pageNumber) {
          return new Response(
            JSON.stringify({
              error: "Missing dateFrom, dateTo, or pageNumber",
            }),
            {
              status: 400,
              headers: { "Content-Type": "application/json" },
            }
          );
        }
        result = await sendApiRequest(
          `${baseUrl}/bookings/search/since/${dateFrom}/until/${dateTo}/page/${pageNumber}`,
          "GET",
          apiKey,
          apiVersion
        );
      }

      if (url.pathname === "/api/search-arrivals") {
        if (!dateFrom || !dateTo || !pageNumber) {
          return new Response(
            JSON.stringify({
              error: "Missing dateFrom, dateTo, or pageNumber",
            }),
            {
              status: 400,
              headers: { "Content-Type": "application/json" },
            }
          );
        }
        result = await sendApiRequest(
          `${baseUrl}/bookings/search/arrivals/since/${dateFrom}/until/${dateTo}/page/${pageNumber}`,
          "GET",
          apiKey,
          apiVersion
        );
      }

      if (url.pathname === "/api/search-departures") {
        if (!dateFrom || !dateTo || !pageNumber) {
          return new Response(
            JSON.stringify({
              error: "Missing dateFrom, dateTo, or pageNumber",
            }),
            {
              status: 400,
              headers: { "Content-Type": "application/json" },
            }
          );
        }
        result = await sendApiRequest(
          `${baseUrl}/bookings/search/departures/since/${dateFrom}/until/${dateTo}/page/${pageNumber}`,
          "GET",
          apiKey,
          apiVersion
        );
      }

      if (url.pathname === "/api/retrieve-notes") {
        if (!bookingRef) {
          return new Response(JSON.stringify({ error: "Missing bookingRef" }), {
            status: 400,
            headers: { "Content-Type": "application/json" },
          });
        }
        result = await sendApiRequest(
          `${baseUrl}/bookings/notes/${bookingRef}`,
          "GET",
          apiKey,
          apiVersion
        );
      }

      if (url.pathname === "/api/update-booking") {
        if (!bookingRef) {
          return new Response(JSON.stringify({ error: "Missing bookingRef" }), {
            status: 400,
            headers: { "Content-Type": "application/json" },
          });
        }
        const requestData = {
          status: requestBody.status,
        };
        result = await sendApiRequest(
          `${baseUrl}/bookings/${bookingRef}`,
          "PUT",
          apiKey,
          apiVersion,
          requestData
        );
      }
      if (url.pathname === "/api/update-multiple-bookings") {
        if (!requestBody.bookings || !Array.isArray(requestBody.bookings)) {
          return new Response(
            JSON.stringify({ error: "Missing or invalid bookings array" }),
            {
              status: 400,
              headers: { "Content-Type": "application/json" },
            }
          );
        }

        const requestData = {
          bookings: requestBody.bookings,
        };

        console.log(
          "Final request to Holiday Taxis:",
          JSON.stringify(requestData, null, 2)
        );

        result = await sendApiRequest(
          `${baseUrl}/bookings`,
          "POST",
          apiKey,
          apiVersion,
          requestData
        );
      }

      if (url.pathname === "/api/reconfirm-bookings") {
        const requestData = {
          bookings: requestBody.bookings,
        };
        result = await sendApiRequest(
          `${baseUrl}/bookings/reconfirm`,
          "POST",
          apiKey,
          apiVersion,
          requestData
        );
      }

      if (url.pathname === "/api/set-driver") {
        if (!bookingRef || !vehicleIdentifier) {
          return new Response(
            JSON.stringify({
              error: "Missing bookingRef or vehicleIdentifier",
            }),
            {
              status: 400,
              headers: { "Content-Type": "application/json" },
            }
          );
        }
        const requestData = {
          driver: requestBody.driver,
          vehicle: requestBody.vehicle,
        };
        result = await sendApiRequest(
          `${baseUrl}/bookings/${bookingRef}/vehicles/${vehicleIdentifier}`,
          "PUT",
          apiKey,
          apiVersion,
          requestData
        );
      }

      if (url.pathname === "/api/set-location") {
        if (!bookingRef || !vehicleIdentifier) {
          return new Response(
            JSON.stringify({
              error: "Missing bookingRef or vehicleIdentifier",
            }),
            {
              status: 400,
              headers: { "Content-Type": "application/json" },
            }
          );
        }
        const requestData = {
          timestamp: requestBody.timestamp,
          location: requestBody.location,
          status: requestBody.status,
        };
        result = await sendApiRequest(
          `${baseUrl}/bookings/${bookingRef}/vehicles/${vehicleIdentifier}/location`,
          "POST",
          apiKey,
          apiVersion,
          requestData
        );
      }

      if (url.pathname === "/api/deallocate-vehicle") {
        if (!bookingRef || !vehicleIdentifier) {
          return new Response(
            JSON.stringify({
              error: "Missing bookingRef or vehicleIdentifier",
            }),
            {
              status: 400,
              headers: { "Content-Type": "application/json" },
            }
          );
        }
        result = await sendApiRequest(
          `${baseUrl}/bookings/${bookingRef}/vehicles/${vehicleIdentifier}`,
          "DELETE",
          apiKey,
          apiVersion
        );
      }

      if (url.pathname === "/api/retrieve-resorts") {
        if (!startAt) {
          return new Response(JSON.stringify({ error: "Missing startAt" }), {
            status: 400,
            headers: { "Content-Type": "application/json" },
          });
        }
        result = await sendApiRequest(
          `${baseUrl}/products/resorts/areas?startAt=${startAt}`,
          "GET",
          apiKey,
          apiVersion
        );
      }

      return new Response(JSON.stringify(result), {
        headers: { "Content-Type": "application/json" },
      });
    }

    return new Response("Not Found", { status: 404 });
  },
});

console.log("Server running at http://localhost:3000");
