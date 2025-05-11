<?php
/**
 * SAMAPE - Gamification functions
 * Functions related to gamification, achievements and points
 */

/**
 * Create default achievements if they don't exist
 * @param PDO $db Database connection
 * @return bool True on success
 */
function create_default_achievements($db) {
    try {
        // Check if we already have achievements
        $stmt = $db->query("SELECT COUNT(*) FROM achievements");
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            return true; // Achievements already exist
        }
        
        // Create the default achievements
        $achievements = [
            [
                'name' => 'Primeiro Serviço',
                'description' => 'Concluiu seu primeiro serviço',
                'icon' => 'fa-tools',
                'points' => 10
            ],
            [
                'name' => 'Técnico Iniciante',
                'description' => 'Concluiu 5 serviços com sucesso',
                'icon' => 'fa-star',
                'points' => 20
            ],
            [
                'name' => 'Técnico Experiente',
                'description' => 'Concluiu 25 serviços com sucesso',
                'icon' => 'fa-medal',
                'points' => 50
            ],
            [
                'name' => 'Mestre Técnico',
                'description' => 'Concluiu 100 serviços com sucesso',
                'icon' => 'fa-trophy',
                'points' => 100
            ],
            [
                'name' => 'Satisfação 5 Estrelas',
                'description' => 'Recebeu avaliação 5 estrelas em um serviço',
                'icon' => 'fa-smile',
                'points' => 15
            ],
            [
                'name' => 'Cliente Feliz',
                'description' => 'Recebeu 10 avaliações 5 estrelas',
                'icon' => 'fa-heart',
                'points' => 30
            ],
            [
                'name' => 'Técnico do Mês',
                'description' => 'Foi o técnico com mais pontos em um mês',
                'icon' => 'fa-calendar-star',
                'points' => 50
            ],
            [
                'name' => 'Especialista',
                'description' => 'Atendeu 10 tipos diferentes de maquinários',
                'icon' => 'fa-cogs',
                'points' => 25
            ],
            [
                'name' => 'Eficiência',
                'description' => 'Completou 5 serviços em menos de 48 horas',
                'icon' => 'fa-tachometer-alt',
                'points' => 20
            ],
            [
                'name' => 'Lenda da Empresa',
                'description' => 'Recebeu todas as outras conquistas',
                'icon' => 'fa-crown',
                'points' => 100
            ]
        ];
        
        $stmt = $db->prepare("INSERT INTO achievements (name, description, icon, points) VALUES (?, ?, ?, ?)");
        
        foreach ($achievements as $achievement) {
            $stmt->execute([
                $achievement['name'], 
                $achievement['description'], 
                $achievement['icon'], 
                $achievement['points']
            ]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error creating default achievements: " . $e->getMessage());
        return false;
    }
}

/**
 * Award an achievement to a user
 * @param PDO $db Database connection
 * @param int $user_id User ID
 * @param int $achievement_id Achievement ID
 * @return bool True on success
 */
function award_achievement($db, $user_id, $achievement_id) {
    try {
        // Check if user already has this achievement
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM user_achievements 
            WHERE user_id = ? AND achievement_id = ?
        ");
        $stmt->execute([$user_id, $achievement_id]);
        
        if ($stmt->fetchColumn() > 0) {
            return false; // User already has this achievement
        }
        
        // Get achievement details
        $stmt = $db->prepare("SELECT points FROM achievements WHERE id = ?");
        $stmt->execute([$achievement_id]);
        $achievement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$achievement) {
            return false; // Achievement doesn't exist
        }
        
        // Get user's associated employee id
        $employee_id = get_employee_id_from_user($db, $user_id);
        
        // Begin transaction
        $db->beginTransaction();
        
        // Award the achievement
        $stmt = $db->prepare("
            INSERT INTO user_achievements (user_id, achievement_id, earned_at) 
            VALUES (?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$user_id, $achievement_id]);
        
        // Add points to the employee if exists
        if ($employee_id) {
            add_points_to_employee($db, $employee_id, $achievement['points']);
        }
        
        // Commit transaction
        $db->commit();
        
        return true;
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error awarding achievement: " . $e->getMessage());
        return false;
    }
}

/**
 * Add points to an employee's stats
 * @param PDO $db Database connection
 * @param int $employee_id Employee ID
 * @param int $points Points to add
 * @return bool True on success
 */
function add_points_to_employee($db, $employee_id, $points) {
    try {
        // Get current points and level
        $stmt = $db->prepare("
            SELECT points, level FROM employee_stats 
            WHERE employee_id = ?
        ");
        $stmt->execute([$employee_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats) {
            // Create new stats record
            $current_points = $points;
            $new_level = calculate_level($points);
            
            $stmt = $db->prepare("
                INSERT INTO employee_stats (employee_id, points, level) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$employee_id, $current_points, $new_level]);
        } else {
            // Update existing stats
            $current_points = $stats['points'] + $points;
            $new_level = calculate_level($current_points);
            
            $stmt = $db->prepare("
                UPDATE employee_stats 
                SET points = ?, level = ? 
                WHERE employee_id = ?
            ");
            $stmt->execute([$current_points, $new_level, $employee_id]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error adding points to employee: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate level based on points
 * @param int $points Points
 * @return int Level
 */
function calculate_level($points) {
    return min(10, max(1, ceil($points / 100)));
}

/**
 * Get the employee ID associated with a user
 * @param PDO $db Database connection
 * @param int $user_id User ID
 * @return int|null Employee ID or null if not found
 */
function get_employee_id_from_user($db, $user_id) {
    try {
        // Get the user's email
        $stmt = $db->prepare("SELECT email FROM usuarios WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return null;
        }
        
        // Find employee with matching email
        $stmt = $db->prepare("SELECT id FROM funcionarios WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$user['email']]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $employee ? $employee['id'] : null;
    } catch (PDOException $e) {
        error_log("Error getting employee ID from user: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all achievements
 * @param PDO $db Database connection
 * @return array Array of achievements
 */
function get_all_achievements($db) {
    try {
        $stmt = $db->query("SELECT * FROM achievements ORDER BY points ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting achievements: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user's achievements
 * @param PDO $db Database connection
 * @param int $user_id User ID
 * @return array User's achievements
 */
function get_user_achievements($db, $user_id) {
    try {
        $stmt = $db->prepare("
            SELECT a.*, ua.earned_at 
            FROM achievements a
            JOIN user_achievements ua ON a.id = ua.achievement_id
            WHERE ua.user_id = ?
            ORDER BY ua.earned_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting user achievements: " . $e->getMessage());
        return [];
    }
}

/**
 * Get leaderboard of employees
 * @param PDO $db Database connection
 * @param string $type Type of leaderboard (points, services, satisfaction)
 * @return array Leaderboard data
 */
function get_leaderboard($db, $type = 'points') {
    try {
        $order_by = "es.points DESC";
        
        if ($type === 'services') {
            $order_by = "es.services_completed DESC";
        } elseif ($type === 'satisfaction') {
            $order_by = "es.avg_satisfaction DESC";
        }
        
        $stmt = $db->prepare("
            SELECT 
                f.id, 
                f.nome, 
                f.cargo,
                COALESCE(es.points, 0) as points,
                COALESCE(es.level, 1) as level,
                COALESCE(es.services_completed, 0) as services_completed,
                COALESCE(es.avg_satisfaction, 0) as avg_satisfaction
            FROM funcionarios f
            LEFT JOIN employee_stats es ON f.id = es.employee_id
            WHERE f.ativo = 1
            ORDER BY $order_by, f.nome ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting leaderboard: " . $e->getMessage());
        return [];
    }
}

/**
 * Get employee stats
 * @param PDO $db Database connection
 * @param int $employee_id Employee ID
 * @return array|null Employee stats or null if not found
 */
function get_employee_stats($db, $employee_id) {
    try {
        $stmt = $db->prepare("
            SELECT * FROM employee_stats 
            WHERE employee_id = ?
        ");
        $stmt->execute([$employee_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats) {
            // Return default stats
            return [
                'employee_id' => $employee_id,
                'points' => 0,
                'level' => 1,
                'services_completed' => 0,
                'avg_satisfaction' => 0
            ];
        }
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Error getting employee stats: " . $e->getMessage());
        return null;
    }
}

/**
 * Update employee stats after service completion
 * @param PDO $db Database connection
 * @param int $order_id Service order ID
 * @param array $employee_ids Array of employee IDs involved
 * @param float $satisfaction_rating Satisfaction rating (0-5)
 * @return bool True on success
 */
function update_employee_stats_after_service($db, $order_id, $employee_ids, $satisfaction_rating = null) {
    if (empty($employee_ids)) {
        return false;
    }
    
    try {
        $db->beginTransaction();
        
        foreach ($employee_ids as $employee_id) {
            // Get current stats
            $stmt = $db->prepare("
                SELECT * FROM employee_stats 
                WHERE employee_id = ?
            ");
            $stmt->execute([$employee_id]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Default values if no stats exist
            $points = 0;
            $level = 1;
            $services_completed = 0;
            $total_ratings = 0;
            $total_rating_value = 0;
            
            if ($stats) {
                $points = $stats['points'];
                $level = $stats['level'];
                $services_completed = $stats['services_completed'];
                $total_ratings = $stats['total_ratings'];
                $total_rating_value = $stats['total_rating_value'];
            }
            
            // Increment services completed
            $services_completed++;
            
            // Add base points for completing a service
            $points_earned = 10;
            $points += $points_earned;
            
            // Add satisfaction rating if provided
            if ($satisfaction_rating !== null && $satisfaction_rating > 0) {
                $total_ratings++;
                $total_rating_value += $satisfaction_rating;
                
                // Give bonus points for high ratings
                if ($satisfaction_rating >= 4) {
                    $bonus_points = round(($satisfaction_rating - 3) * 5);
                    $points += $bonus_points;
                    $points_earned += $bonus_points;
                }
            }
            
            // Calculate new level
            $new_level = calculate_level($points);
            
            // Calculate new average satisfaction
            $avg_satisfaction = ($total_ratings > 0) ? 
                round($total_rating_value / $total_ratings, 1) : 0;
            
            // Update or insert stats
            if ($stats) {
                $stmt = $db->prepare("
                    UPDATE employee_stats 
                    SET points = ?, 
                        level = ?, 
                        services_completed = ?,
                        total_ratings = ?,
                        total_rating_value = ?,
                        avg_satisfaction = ?
                    WHERE employee_id = ?
                ");
                $stmt->execute([
                    $points, 
                    $new_level, 
                    $services_completed,
                    $total_ratings,
                    $total_rating_value,
                    $avg_satisfaction,
                    $employee_id
                ]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO employee_stats 
                    (employee_id, points, level, services_completed, total_ratings, total_rating_value, avg_satisfaction) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $employee_id, 
                    $points, 
                    $new_level, 
                    $services_completed,
                    $total_ratings,
                    $total_rating_value,
                    $avg_satisfaction
                ]);
            }
            
            // Check for service count achievements
            $user_id = get_user_id_from_employee($db, $employee_id);
            if ($user_id) {
                check_service_count_achievements($db, $user_id, $services_completed);
                
                // Check for satisfaction achievements if rating provided
                if ($satisfaction_rating !== null && $satisfaction_rating == 5) {
                    check_satisfaction_achievements($db, $user_id, $total_ratings);
                }
            }
            
            // Record points earned for this service
            $stmt = $db->prepare("
                INSERT INTO employee_points_history 
                (employee_id, order_id, points, reason, earned_at) 
                VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                $employee_id,
                $order_id,
                $points_earned,
                'Conclusão de ordem de serviço'
            ]);
        }
        
        $db->commit();
        return true;
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error updating employee stats: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user ID from employee ID
 * @param PDO $db Database connection
 * @param int $employee_id Employee ID
 * @return int|null User ID or null if not found
 */
function get_user_id_from_employee($db, $employee_id) {
    try {
        // Get employee email
        $stmt = $db->prepare("SELECT email FROM funcionarios WHERE id = ?");
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee || empty($employee['email'])) {
            return null;
        }
        
        // Find user with matching email
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$employee['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user ? $user['id'] : null;
    } catch (PDOException $e) {
        error_log("Error getting user ID from employee: " . $e->getMessage());
        return null;
    }
}

/**
 * Check and award service count achievements
 * @param PDO $db Database connection
 * @param int $user_id User ID
 * @param int $services_completed Number of services completed
 * @return bool True if any achievement awarded
 */
function check_service_count_achievements($db, $user_id, $services_completed) {
    $awarded = false;
    
    try {
        // Define achievements to check
        $achievements = [
            ['count' => 1, 'name' => 'Primeiro Serviço'],
            ['count' => 5, 'name' => 'Técnico Iniciante'],
            ['count' => 25, 'name' => 'Técnico Experiente'],
            ['count' => 100, 'name' => 'Mestre Técnico']
        ];
        
        foreach ($achievements as $achievement) {
            if ($services_completed >= $achievement['count']) {
                // Get the achievement ID
                $stmt = $db->prepare("SELECT id FROM achievements WHERE name = ?");
                $stmt->execute([$achievement['name']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    // Award the achievement
                    $achievement_id = $result['id'];
                    if (award_achievement($db, $user_id, $achievement_id)) {
                        $awarded = true;
                    }
                }
            }
        }
        
        return $awarded;
    } catch (PDOException $e) {
        error_log("Error checking service count achievements: " . $e->getMessage());
        return false;
    }
}

/**
 * Check and award satisfaction rating achievements
 * @param PDO $db Database connection
 * @param int $user_id User ID
 * @param int $five_star_count Number of 5-star ratings
 * @return bool True if any achievement awarded
 */
function check_satisfaction_achievements($db, $user_id, $five_star_count) {
    $awarded = false;
    
    try {
        // Define achievements to check
        $achievements = [
            ['count' => 1, 'name' => 'Satisfação 5 Estrelas'],
            ['count' => 10, 'name' => 'Cliente Feliz']
        ];
        
        foreach ($achievements as $achievement) {
            if ($five_star_count >= $achievement['count']) {
                // Get the achievement ID
                $stmt = $db->prepare("SELECT id FROM achievements WHERE name = ?");
                $stmt->execute([$achievement['name']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    // Award the achievement
                    $achievement_id = $result['id'];
                    if (award_achievement($db, $user_id, $achievement_id)) {
                        $awarded = true;
                    }
                }
            }
        }
        
        return $awarded;
    } catch (PDOException $e) {
        error_log("Error checking satisfaction achievements: " . $e->getMessage());
        return false;
    }
}

/**
 * Setup gamification database tables
 * @param PDO $db Database connection
 * @return bool True on success
 */
function setup_gamification_tables($db) {
    try {
        // Create achievements table
        $db->exec("
            CREATE TABLE IF NOT EXISTS achievements (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT NOT NULL,
                icon VARCHAR(50) NOT NULL,
                points INTEGER NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create user achievements table
        $db->exec("
            CREATE TABLE IF NOT EXISTS user_achievements (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL,
                achievement_id INTEGER NOT NULL,
                earned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, achievement_id)
            )
        ");
        
        // Create employee stats table
        $db->exec("
            CREATE TABLE IF NOT EXISTS employee_stats (
                id SERIAL PRIMARY KEY,
                employee_id INTEGER NOT NULL UNIQUE,
                points INTEGER NOT NULL DEFAULT 0,
                level INTEGER NOT NULL DEFAULT 1,
                services_completed INTEGER NOT NULL DEFAULT 0,
                total_ratings INTEGER NOT NULL DEFAULT 0,
                total_rating_value FLOAT NOT NULL DEFAULT 0,
                avg_satisfaction FLOAT NOT NULL DEFAULT 0,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create employee points history table
        $db->exec("
            CREATE TABLE IF NOT EXISTS employee_points_history (
                id SERIAL PRIMARY KEY,
                employee_id INTEGER NOT NULL,
                order_id INTEGER,
                points INTEGER NOT NULL,
                reason VARCHAR(255) NOT NULL,
                earned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Create default achievements
        create_default_achievements($db);
        
        // Add satisfaction rating column to service orders if it doesn't exist
        $result = $db->query("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'ordens_servico' AND column_name = 'satisfaction_rating'
        ");
        
        if ($result->rowCount() == 0) {
            $db->exec("
                ALTER TABLE ordens_servico 
                ADD COLUMN satisfaction_rating FLOAT
            ");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error setting up gamification tables: " . $e->getMessage());
        return false;
    }
}