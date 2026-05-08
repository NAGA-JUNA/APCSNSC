-- APCSNSC Template-Based ID Card System tables

CREATE TABLE IF NOT EXISTS card_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    theme_name VARCHAR(80) NOT NULL DEFAULT 'default',
    front_template VARCHAR(255) DEFAULT NULL,
    back_template VARCHAR(255) DEFAULT NULL,
    watermark_image VARCHAR(255) DEFAULT NULL,
    hologram_overlay VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_card_template_theme (theme_name)
);

CREATE TABLE IF NOT EXISTS card_template_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    theme_name VARCHAR(80) NOT NULL DEFAULT 'default',
    field_name VARCHAR(80) NOT NULL,
    side ENUM('front','back') NOT NULL,
    pos_x INT NOT NULL DEFAULT 20,
    pos_y INT NOT NULL DEFAULT 20,
    font_size INT NOT NULL DEFAULT 12,
    color VARCHAR(20) NOT NULL DEFAULT '#1b2f44',
    align ENUM('left','center','right') NOT NULL DEFAULT 'left',
    width INT NOT NULL DEFAULT 120,
    height INT NOT NULL DEFAULT 24,
    font_weight VARCHAR(10) NOT NULL DEFAULT '600',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_template_field (theme_name, field_name),
    KEY idx_template_side (theme_name, side)
);

INSERT INTO card_templates (theme_name, front_template, back_template, is_active)
SELECT 'default', 'uploads/templates/front-template.png', 'uploads/templates/back-template.png', 1
WHERE NOT EXISTS (SELECT 1 FROM card_templates WHERE theme_name = 'default');

INSERT INTO card_template_positions (theme_name, field_name, side, pos_x, pos_y, font_size, color, align, width, height, font_weight)
SELECT 'default', 'photo', 'front', 118, 126, 12, '#1b2f44', 'center', 102, 126, '600'
WHERE NOT EXISTS (SELECT 1 FROM card_template_positions WHERE theme_name='default' AND field_name='photo');

INSERT INTO card_template_positions (theme_name, field_name, side, pos_x, pos_y, font_size, color, align, width, height, font_weight)
SELECT 'default', 'full_name', 'front', 55, 258, 20, '#1f4f42', 'center', 230, 38, '800'
WHERE NOT EXISTS (SELECT 1 FROM card_template_positions WHERE theme_name='default' AND field_name='full_name');

INSERT INTO card_template_positions (theme_name, field_name, side, pos_x, pos_y, font_size, color, align, width, height, font_weight)
SELECT 'default', 'member_id', 'front', 82, 300, 12, '#ffffff', 'center', 176, 28, '700'
WHERE NOT EXISTS (SELECT 1 FROM card_template_positions WHERE theme_name='default' AND field_name='member_id');

INSERT INTO card_template_positions (theme_name, field_name, side, pos_x, pos_y, font_size, color, align, width, height, font_weight)
SELECT 'default', 'district', 'front', 26, 350, 11, '#1f2932', 'left', 136, 28, '600'
WHERE NOT EXISTS (SELECT 1 FROM card_template_positions WHERE theme_name='default' AND field_name='district');

INSERT INTO card_template_positions (theme_name, field_name, side, pos_x, pos_y, font_size, color, align, width, height, font_weight)
SELECT 'default', 'hospital', 'front', 178, 350, 11, '#1f2932', 'left', 136, 28, '600'
WHERE NOT EXISTS (SELECT 1 FROM card_template_positions WHERE theme_name='default' AND field_name='hospital');

INSERT INTO card_template_positions (theme_name, field_name, side, pos_x, pos_y, font_size, color, align, width, height, font_weight)
SELECT 'default', 'role', 'front', 26, 386, 10, '#1f2932', 'left', 136, 28, '600'
WHERE NOT EXISTS (SELECT 1 FROM card_template_positions WHERE theme_name='default' AND field_name='role');

INSERT INTO card_template_positions (theme_name, field_name, side, pos_x, pos_y, font_size, color, align, width, height, font_weight)
SELECT 'default', 'join_date', 'front', 178, 386, 10, '#1f2932', 'left', 136, 28, '600'
WHERE NOT EXISTS (SELECT 1 FROM card_template_positions WHERE theme_name='default' AND field_name='join_date');

INSERT INTO card_template_positions (theme_name, field_name, side, pos_x, pos_y, font_size, color, align, width, height, font_weight)
SELECT 'default', 'valid_till', 'front', 178, 422, 10, '#1f2932', 'left', 136, 28, '600'
WHERE NOT EXISTS (SELECT 1 FROM card_template_positions WHERE theme_name='default' AND field_name='valid_till');

INSERT INTO card_template_positions (theme_name, field_name, side, pos_x, pos_y, font_size, color, align, width, height, font_weight)
SELECT 'default', 'qr_code', 'back', 104, 135, 12, '#1b2f44', 'center', 130, 130, '600'
WHERE NOT EXISTS (SELECT 1 FROM card_template_positions WHERE theme_name='default' AND field_name='qr_code');

INSERT INTO card_template_positions (theme_name, field_name, side, pos_x, pos_y, font_size, color, align, width, height, font_weight)
SELECT 'default', 'website', 'back', 44, 290, 10, '#22323d', 'left', 250, 20, '600'
WHERE NOT EXISTS (SELECT 1 FROM card_template_positions WHERE theme_name='default' AND field_name='website');

INSERT INTO card_template_positions (theme_name, field_name, side, pos_x, pos_y, font_size, color, align, width, height, font_weight)
SELECT 'default', 'helpline', 'back', 44, 315, 10, '#22323d', 'left', 250, 20, '600'
WHERE NOT EXISTS (SELECT 1 FROM card_template_positions WHERE theme_name='default' AND field_name='helpline');

INSERT INTO card_template_positions (theme_name, field_name, side, pos_x, pos_y, font_size, color, align, width, height, font_weight)
SELECT 'default', 'email', 'back', 44, 340, 10, '#22323d', 'left', 250, 20, '600'
WHERE NOT EXISTS (SELECT 1 FROM card_template_positions WHERE theme_name='default' AND field_name='email');

INSERT INTO card_template_positions (theme_name, field_name, side, pos_x, pos_y, font_size, color, align, width, height, font_weight)
SELECT 'default', 'signature', 'back', 112, 438, 12, '#1b2f44', 'center', 120, 26, '600'
WHERE NOT EXISTS (SELECT 1 FROM card_template_positions WHERE theme_name='default' AND field_name='signature');

INSERT INTO card_template_positions (theme_name, field_name, side, pos_x, pos_y, font_size, color, align, width, height, font_weight)
SELECT 'default', 'serial_number', 'back', 104, 515, 10, '#f2e6c4', 'center', 130, 20, '700'
WHERE NOT EXISTS (SELECT 1 FROM card_template_positions WHERE theme_name='default' AND field_name='serial_number');
