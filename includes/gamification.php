<?php
/**
 * SAMAPE - Gamification functions
 * Handles gamification related functionality like achievements, points and levels
 */

/**
 * Get all available achievements
 * @param PDO $db Database connection
 * @return array Array of achievements
 */
function get_all_achievements($db) {
    try {
        $stmt = $db->prepare("SELECT * FROM achievements ORDER BY points ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error fetching achievements: " . $e->getMessage());
        return [];
    }
}

/**
 * Get achievements earned by a user
 * @param PDO $db Database connection
 * @param int $user_id User ID
 * @return array Array of user's achievements
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
    } catch(PDOException $e) {
        error_log("Error fetching user achievements: " . $e->getMessage());
        return [];
    }
}

/**
 * Award an achievement to a user
 * @param PDO $db Database connection
 * @param int $user_id User ID
 * @param int $achievement_id Achievement ID
 * @return bool True if successful, false otherwise
 */
function award_achievement($db, $user_id, $achievement_id) {
    try {
        // Check if user already has this achievement
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM user_achievements WHERE user_id = ? AND achievement_id = ?");
        $stmt->execute([$user_id, $achievement_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            // User already has this achievement
            return false;
        }
        
        // Get the achievement details
        $stmt = $db->prepare("SELECT * FROM achievements WHERE id = ?");
        $stmt->execute([$achievement_id]);
        $achievement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$achievement) {
            return false;
        }
        
        // Award the achievement
        $stmt = $db->prepare("INSERT INTO user_achievements (user_id, achievement_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $achievement_id]);
        
        // Add points to employee stats
        $employee_id = get_employee_id_from_user($db, $user_id);
        if ($employee_id) {
            add_points_to_employee($db, $employee_id, $achievement['points']);
        }
        
        return true;
    } catch(PDOException $e) {
        error_log("Error awarding achievement: " . $e->getMessage());
        return false;
    }
}

/**
 * Get employee ID from user ID
 * @param PDO $db Database connection
 * @param int $user_id User ID
 * @return int|null Employee ID or null if not found
 */
function get_employee_id_from_user($db, $user_id) {
    try {
        // This is a simplified approach - in a real scenario, you might have a more complex
        // relationship between users and employees
        $stmt = $db->prepare("
            SELECT f.id 
            FROM funcionarios f
            JOIN usuarios u ON LOWER(f.email) = LOWER(u.email)
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['id'] : null;
    } catch(PDOException $e) {
        error_log("Error getting employee ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Add points to an employee and update their level
 * @param PDO $db Database connection
 * @param int $employee_id Employee ID
 * @param int $points Points to add
 * @return bool True if successful, false otherwise
 */
function add_points_to_employee($db, $employee_id, $points) {
    try {
        // Check if employee stats exists
        $stmt = $db->prepare("SELECT * FROM employee_stats WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats) {
            // Create new stats record
            $stmt = $db->prepare("INSERT INTO employee_stats (employee_id, points) VALUES (?, ?)");
            $stmt->execute([$employee_id, $points]);
            $current_points = $points;
        } else {
            // Update existing stats
            $current_points = $stats['points'] + $points;
            $stmt = $db->prepare("UPDATE employee_stats SET points = points + ?, last_updated = CURRENT_TIMESTAMP WHERE employee_id = ?");
            $stmt->execute([$points, $employee_id]);
        }
        
        // Update the level based on points
        $new_level = calculate_level_from_points($current_points);
        $stmt = $db->prepare("UPDATE employee_stats SET level = ? WHERE employee_id = ?");
        $stmt->execute([$new_level, $employee_id]);
        
        return true;
    } catch(PDOException $e) {
        error_log("Error adding points to employee: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate level based on points
 * @param int $points Total points
 * @return int Level
 */
function calculate_level_from_points($points) {
    // Simple level calculation: level = 1 + points / 100 (max level 10)
    $level = 1 + floor($points / 100);
    return min($level, 10);
}

/**
 * Get employee stats
 * @param PDO $db Database connection
 * @param int $employee_id Employee ID
 * @return array|null Employee stats or null if not found
 */
function get_employee_stats($db, $employee_id) {
    try {
        $stmt = $db->prepare("SELECT * FROM employee_stats WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error getting employee stats: " . $e->getMessage());
        return null;
    }
}

/**
 * Update employee stats when a service is completed
 * @param PDO $db Database connection
 * @param int $employee_id Employee ID
 * @param int $service_id Service order ID
 * @param int $satisfaction_rating Satisfaction rating (1-5)
 * @return bool True if successful, false otherwise
 */
function update_employee_stats_for_completed_service($db, $employee_id, $service_id, $satisfaction_rating = null) {
    try {
        // Begin transaction
        $db->beginTransaction();
        
        // Check if employee stats exists
        $stmt = $db->prepare("SELECT * FROM employee_stats WHERE employee_id = ?");
        $stmt->execute([$employee_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats) {
            // Create new stats record
            $stmt = $db->prepare("INSERT INTO employee_stats (employee_id, services_completed) VALUES (?, 1)");
            $stmt->execute([$employee_id]);
        } else {
            // Update existing stats for completed service
            $stmt = $db->prepare("UPDATE employee_stats SET services_completed = services_completed + 1, last_updated = CURRENT_TIMESTAMP WHERE employee_id = ?");
            $stmt->execute([$employee_id]);
        }
        
        // If satisfaction rating is provided, update that as well
        if ($satisfaction_rating !== null && $satisfaction_rating >= 1 && $satisfaction_rating <= 5) {
            // Update service order
            $stmt = $db->prepare("UPDATE ordens_servico SET satisfaction_rating = ? WHERE id = ?");
            $stmt->execute([$satisfaction_rating, $service_id]);
            
            // Update average satisfaction for employee
            $stmt = $db->prepare("
                SELECT AVG(satisfaction_rating) as avg_rating 
                FROM ordens_servico os
                JOIN os_funcionarios osf ON os.id = osf.ordem_id
                WHERE osf.funcionario_id = ? AND os.satisfaction_rating IS NOT NULL
            ");
            $stmt->execute([$employee_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['avg_rating'])) {
                $stmt = $db->prepare("UPDATE employee_stats SET avg_satisfaction = ? WHERE employee_id = ?");
                $stmt->execute([$result['avg_rating'], $employee_id]);
            }
        }
        
        // Award points based on the service completion
        add_points_to_employee($db, $employee_id, 10); // Base points for completing a service
        
        // Check for on-time completion achievement
        $stmt = $db->prepare("
            SELECT estimated_hours, actual_hours FROM ordens_servico WHERE id = ?
        ");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($service && $service['estimated_hours'] && $service['actual_hours'] && 
            $service['actual_hours'] <= $service['estimated_hours']) {
            // Service completed on time or early - award additional points
            add_points_to_employee($db, $employee_id, 5);
        }
        
        // Check achievements
        check_achievements_for_employee($db, $employee_id);
        
        // Commit transaction
        $db->commit();
        
        return true;
    } catch(PDOException $e) {
        // Rollback transaction
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error updating employee stats: " . $e->getMessage());
        return false;
    }
}

/**
 * Check and award achievements for an employee
 * @param PDO $db Database connection
 * @param int $employee_id Employee ID
 * @return array Array of awarded achievement IDs
 */
function check_achievements_for_employee($db, $employee_id) {
    try {
        $awarded = [];
        
        // Get the user ID for this employee
        $stmt = $db->prepare("
            SELECT u.id
            FROM usuarios u
            JOIN funcionarios f ON LOWER(u.email) = LOWER(f.email)
            WHERE f.id = ?
        ");
        $stmt->execute([$employee_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return $awarded;
        }
        
        $user_id = $user['id'];
        
        // Get employee stats
        $stats = get_employee_stats($db, $employee_id);
        
        if (!$stats) {
            return $awarded;
        }
        
        // Check for service milestone achievements
        $milestones = [1, 5, 10, 25, 50, 100];
        foreach ($milestones as $milestone) {
            if ($stats['services_completed'] >= $milestone) {
                // Check if this milestone has an achievement
                $stmt = $db->prepare("
                    SELECT id FROM achievements 
                    WHERE name = ? OR name LIKE ?
                ");
                $stmt->execute([
                    "Complete $milestone Service" . ($milestone > 1 ? 's' : ''),
                    "Complete $milestone Service%"
                ]);
                $achievement = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($achievement) {
                    if (award_achievement($db, $user_id, $achievement['id'])) {
                        $awarded[] = $achievement['id'];
                    }
                }
            }
        }
        
        // Check for rating achievements
        if ($stats['avg_satisfaction'] >= 4.5) {
            $stmt = $db->prepare("
                SELECT id FROM achievements 
                WHERE name LIKE '%Excellence%' OR name LIKE '%Rating%'
            ");
            $stmt->execute();
            while ($achievement = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (award_achievement($db, $user_id, $achievement['id'])) {
                    $awarded[] = $achievement['id'];
                }
            }
        }
        
        // Check for level achievements
        $level_milestones = [3, 5, 10];
        foreach ($level_milestones as $level) {
            if ($stats['level'] >= $level) {
                $stmt = $db->prepare("
                    SELECT id FROM achievements 
                    WHERE name LIKE ?
                ");
                $stmt->execute(["Reach Level $level%"]);
                $achievement = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($achievement) {
                    if (award_achievement($db, $user_id, $achievement['id'])) {
                        $awarded[] = $achievement['id'];
                    }
                }
            }
        }
        
        return $awarded;
    } catch(PDOException $e) {
        error_log("Error checking achievements: " . $e->getMessage());
        return [];
    }
}

/**
 * Get leaderboard data
 * @param PDO $db Database connection
 * @param string $type Type of leaderboard ('points', 'services', 'satisfaction')
 * @param int $limit Number of employees to include
 * @return array Leaderboard data
 */
function get_leaderboard($db, $type = 'points', $limit = 10) {
    try {
        $order_by = 'es.points DESC';
        
        if ($type === 'services') {
            $order_by = 'es.services_completed DESC';
        } else if ($type === 'satisfaction') {
            $order_by = 'es.avg_satisfaction DESC';
        }
        
        $stmt = $db->prepare("
            SELECT 
                f.id,
                f.nome,
                f.cargo,
                es.points,
                es.level,
                es.services_completed,
                es.avg_satisfaction
            FROM funcionarios f
            JOIN employee_stats es ON f.id = es.employee_id
            ORDER BY $order_by
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error getting leaderboard: " . $e->getMessage());
        return [];
    }
}

/**
 * Create default achievements
 * @param PDO $db Database connection
 */
function create_default_achievements($db) {
    try {
        $default_achievements = [
            ['First Service', 'Complete your first service order', 'fa-star', 10],
            ['Service Starter', 'Complete 5 service orders', 'fa-tools', 25],
            ['Service Professional', 'Complete 10 service orders', 'fa-wrench', 50],
            ['Service Expert', 'Complete 25 service orders', 'fa-cogs', 100],
            ['Service Master', 'Complete 50 service orders', 'fa-award', 200],
            ['Service Legend', 'Complete 100 service orders', 'fa-trophy', 500],
            ['On Time Hero', 'Complete 5 service orders on time', 'fa-clock', 30],
            ['Efficiency Expert', 'Complete 10 service orders faster than estimated', 'fa-bolt', 75],
            ['Customer Satisfaction', 'Maintain a 4.0+ average satisfaction rating', 'fa-smile', 50],
            ['Service Excellence', 'Maintain a 4.5+ average satisfaction rating', 'fa-medal', 100],
            ['Rising Star', 'Reach Level 3', 'fa-level-up-alt', 30],
            ['Technical Expert', 'Reach Level 5', 'fa-user-graduate', 75],
            ['Technical Master', 'Reach Level 10', 'fa-crown', 200]
        ];
        
        // Check if achievements already exist
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM achievements");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            $stmt = $db->prepare("INSERT INTO achievements (name, description, icon, points) VALUES (?, ?, ?, ?)");
            
            foreach ($default_achievements as $achievement) {
                $stmt->execute($achievement);
            }
        }
    } catch(PDOException $e) {
        error_log("Error creating default achievements: " . $e->getMessage());
    }
}
?>