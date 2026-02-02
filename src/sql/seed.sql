-- Assembly Line Production Tracking - Seed Data
-- USE kaizenap_assyline_db;

-- Default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('default_shift_start_sun_fri', '08:30', 'Default shift start time for Sunday to Friday'),
('default_shift_end_sun_fri', '21:00', 'Default shift end time for Sunday to Friday'),
('default_shift_start_sat', '07:00', 'Default shift start time for Saturday'),
('default_shift_end_sat', '15:30', 'Default shift end time for Saturday'),
('default_lunch_start', '12:30', 'Default lunch break start'),
('default_lunch_end', '13:00', 'Default lunch break end')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Production groups
INSERT INTO production_groups (name, default_cells, expected_output_per_cell_per_hour, display_order) VALUES
('GROUP 1 (ROMAN)', 4, 6.00, 1),
('GROUP 2 (GANESH)', 6, 8.00, 2),
('GROUP 3 (PAWAR)', 4, 6.00, 3),
('GROUP 4 (GAIKWAD MADAM)', 4, 6.00, 4);

-- Default time slots - Sunday to Friday (6 slots)
INSERT INTO default_time_slots (day_type, slot_number, start_time, end_time, label) VALUES
('sun_fri', 1, '08:30', '10:30', 'Slot 1'),
('sun_fri', 2, '10:30', '12:30', 'Slot 2'),
('sun_fri', 3, '13:00', '15:00', 'Slot 3'),
('sun_fri', 4, '15:00', '17:00', 'Slot 4'),
('sun_fri', 5, '17:00', '19:00', 'Slot 5'),
('sun_fri', 6, '19:00', '21:00', 'Slot 6');

-- Default time slots - Saturday (3 slots)
INSERT INTO default_time_slots (day_type, slot_number, start_time, end_time, label) VALUES
('sat', 1, '07:00', '10:00', 'Slot 1'),
('sat', 2, '10:00', '12:30', 'Slot 2'),
('sat', 3, '13:00', '15:30', 'Slot 3');

-- Default lunch break
INSERT INTO breaks (break_type, label, is_default, day_type, start_time, end_time) VALUES
('lunch', 'Lunch Break', 1, 'sun_fri', '12:30', '13:00'),
('lunch', 'Lunch Break', 1, 'sat', '12:30', '13:00');

-- Default deficit reasons
INSERT INTO deficit_reasons (reason_text, display_order) VALUES
('Cover Shortage', 1),
('Foam Shortage', 2),
('Powder Coating Shortage', 3),
('Fabrication Shortage', 4),
('Machine Breakdown', 5),
('Manpower Shortage', 6),
('Quality Issue', 7),
('Power Failure', 8),
('Other', 9);
