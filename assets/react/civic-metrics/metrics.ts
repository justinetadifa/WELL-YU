import type { MetricKind } from './MetricArt';

export interface CivicMetric {
  id: MetricKind;
  value: string;
  unit?: string;
  label: string;
  source: string;
  caption: string;
  substat?: string;
  growth?: string;
  theme: string;
}
export interface CivicCategory { id: string; title: string; metrics: readonly CivicMetric[] }

// Figures and additions supplied in the civic-widget brief. The SVGs are
// conceptual illustrations; their coordinates do not encode additional data.
export const civicCategories: readonly CivicCategory[] = [
  { id: 'people', title: 'Community & reach', metrics: [
    { id: 'residents', value: '128,024', label: 'Residents Served', source: 'PSA Census · 59 Barangays · 10-Min Care Target', caption: '59 Barangays · 10-Min Care Reach', theme: 'crimson' },
    { id: 'households', value: '59', label: 'Barangay Health Stations', source: 'City Health Office (CHO) · Mon–Fri 8 AM–5 PM', caption: 'Free Primary Baseline · Closes 5:00 PM', theme: 'peach' },
  ] },
  { id: 'business', title: 'Clinical anchors', metrics: [
    { id: 'businesses', value: '4', label: 'Tertiary & Secondary Hospitals', source: 'DOH Region I · ITRMC, Lorma, Bethany & LUMED', caption: 'ITRMC, Lorma, Bethany & LUMED', growth: '24/7 Care', theme: 'amber' },
    { id: 'finance', value: '4', label: 'Freestanding Dialysis Centers', source: 'Hemotek, LUCKD, Avitus & FMC Renal Care', caption: '4-hr Session Caregiver Respite Gap', theme: 'navy' },
  ] },
  { id: 'services', title: 'Care continuum & gaps', metrics: [
    { id: 'education', value: '1', label: 'PT & Pain Center (Closes 6 PM)', source: 'PMP Pain Center Sevilla + DOH-TRC Parian', caption: 'Evening (6–9 PM) & Step-Free Access Deficit', theme: 'cobalt' },
    { id: 'health', value: '292', label: 'Health & Wellness Facilities', source: 'DOH / City Health Office / BPLO', caption: 'Pharmacies, Dental, Rehab & Gyms', substat: 'Public BHS/RHU: 84 · Private: 208', theme: 'mint' },
  ] },
  { id: 'connections', title: 'Healthy corridors & EMS', metrics: [
    { id: 'land', value: '4', unit: 'nodes', label: 'Wellness Parks & Trails', source: 'Poro Baywalk, City Plaza, Pagoda & Botanical', caption: 'Poro Baywalk, Pagoda & Cadaclan', theme: 'forest' },
    { id: 'roads', value: '3', unit: 'hubs', label: 'DRRMO & Emergency EMS Hubs', source: 'CDRRMO, PDRRMO La Union & PH Red Cross', caption: 'Flood-Free Ambulance Continuity', theme: 'slate' },
  ] },
];

export const civicMetrics = civicCategories.flatMap(category => category.metrics.map(metric => ({ ...metric, category: category.title, categoryId: category.id })));
export type FullMetric = typeof civicMetrics[number];
