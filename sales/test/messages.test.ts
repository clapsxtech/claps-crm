import { describe, expect, it } from "vitest";
import expected from "./erp-messages.json";
import { demoParentMessage, demoTeacherMessage, joinedMessage, teacherProfileMessage, whatsappMessage } from "../src/lib/messages";

// erp-messages.json was produced by running the ERP's own SalesPipeline (PHP) on these inputs.
const business = { name: "Claps Learn", timezone: "Asia/Kolkata" };
const demo = { lead_name: "Aarav Sharma", canonical_student_name: null, subject_name: "Mathematics", scheduled_on: "2026-11-21", scheduled_time: "17:30", timezone: "", teacher_name: "Lakshmi Rao", duration_minutes: 60, meet_link: "https://meet.google.com/abc", lead_grade: "Grade 9", lead_syllabus: "CBSE", matching_grade: null, matching_syllabus: null, matching_medium: "English" } as any;
const demo2 = { lead_name: "", canonical_student_name: null, subject_name: null, scheduled_on: "2026-11-22", scheduled_time: "09:05", timezone: "Asia/Qatar", teacher_name: null, duration_minutes: null, meet_link: null, lead_grade: null, lead_syllabus: null } as any;
const lead = { name: "Kabir Khan", canonical_student_name: "Kabir A Khan", canonical_student_code: "ST-77", admission_code: "CL-1042", joined_on: "2026-11-10", grade: "Grade 7", syllabus: "CBSE", subject: "Maths", phone: "+91 9800000006", student_phone: null, source: "Website", assigned_name: "Asha Menon", learning_requirement: "Algebra", concern: null } as any;
const teacher = { name: "Lakshmi Rao", code: "CT-104", category: "Advanced", levels: "UP, HS", subjects: "Mathematics, Physics", classes: "9 CBSE", mediums: "English", qualification: null } as any;

describe("WhatsApp messages match the ERP's SalesPipeline exactly", () => {
  it("demo details for the parent", () => {
    expect(demoParentMessage(demo, business)).toBe(expected.parent);
    expect(demoParentMessage(demo2, business)).toBe(expected.parent2);
  });
  it("demo details for the teacher", () => {
    expect(demoTeacherMessage(demo, business)).toBe(expected.teacher);
    expect(demoTeacherMessage(demo2, business)).toBe(expected.teacher2);
  });
  it("joined summary for the admin group", () => expect(joinedMessage(lead, business)).toBe(expected.joined));
  it("teacher profile", () => expect(teacherProfileMessage(teacher, business)).toBe(expected.profile));
  it("builds chat and share links", () => {
    expect(whatsappMessage("https://wa.me/919800000001", "Hi & bye")).toBe("https://wa.me/919800000001?text=Hi%20%26%20bye");
    expect(whatsappMessage(null, "x")).toBe("https://wa.me/?text=x");
  });
});
