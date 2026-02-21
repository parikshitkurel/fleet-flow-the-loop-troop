import express from "express";
import cookieParser from "cookie-parser";
import cors from "cors";

import authRoutes from "./routes/authRoutes.routes.js";
import patientRoutes from "./routes/patient.routes.js";
import appointmentRoutes from "./routes/appointment.routes.js";
import treatmentRoutes from "./routes/treatment.routes.js";
import wardRoutes from "./routes/ward.routes.js";
import billingRoutes from "./routes/billing.routes.js";
import doctorRoutes from "./routes/doctor.routes.js";
import salaryRoutes from "./routes/salary.routes.js";
import dashboardRoutes from "./routes/dashboard.routes.js";

const app = express();

app.use(cors({
  origin: process.env.CORS_ORIGIN || "http://localhost:5500",
  credentials: true
}));

app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(cookieParser());

app.use("/api/auth", authRoutes);
app.use("/api/patients", patientRoutes);
app.use("/api/appointments", appointmentRoutes);
app.use("/api/treatments", treatmentRoutes);
app.use("/api/wards", wardRoutes);
app.use("/api/billing", billingRoutes);
app.use("/api/doctor", doctorRoutes);
app.use("/api/salary", salaryRoutes);
app.use("/api/dashboard", dashboardRoutes);

export { app };