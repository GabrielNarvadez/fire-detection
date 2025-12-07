"""
Database Bridge Module
Ensures smooth data flow: Python Detection -> SQLite -> PHP -> Frontend
"""

import sqlite3
import json
import os
from datetime import datetime

DATABASE_PATH = "fire_detection.db"

def verify_image_exists(image_path):
    """Check if image file actually exists"""
    if image_path and os.path.exists(image_path):
        return image_path
    return None

def get_latest_detection_with_image():
    """Get most recent detection with valid image path"""
    conn = sqlite3.connect(DATABASE_PATH)
    conn.row_factory = sqlite3.Row
    cursor = conn.cursor()
    
    cursor.execute("""
        SELECT * FROM detections 
        WHERE image_path IS NOT NULL 
        ORDER BY timestamp DESC 
        LIMIT 1
    """)
    
    row = cursor.fetchone()
    conn.close()
    
    if row:
        detection = dict(row)
        detection['image_path'] = verify_image_exists(detection['image_path'])
        return detection
    return None

def fix_detection_timestamps():
    """Convert any malformed timestamps to proper ISO format"""
    conn = sqlite3.connect(DATABASE_PATH)
    cursor = conn.cursor()
    
    cursor.execute("SELECT id, timestamp FROM detections")
    rows = cursor.fetchall()
    
    for row in rows:
        det_id, ts = row
        try:
            # Try parsing the timestamp
            dt = datetime.fromisoformat(ts.replace(' ', 'T'))
            # Re-save in correct format
            cursor.execute(
                "UPDATE detections SET timestamp = ? WHERE id = ?",
                (dt.isoformat(), det_id)
            )
        except:
            pass
    
    conn.commit()
    conn.close()

def cleanup_missing_images():
    """Remove detection records where image file is missing"""
    conn = sqlite3.connect(DATABASE_PATH)
    cursor = conn.cursor()
    
    cursor.execute("SELECT id, image_path FROM detections WHERE image_path IS NOT NULL")
    rows = cursor.fetchall()
    
    removed = 0
    for row in rows:
        det_id, img_path = row
        if not os.path.exists(img_path):
            cursor.execute("DELETE FROM detections WHERE id = ?", (det_id,))
            removed += 1
    
    conn.commit()
    conn.close()
    
    return removed

def get_camera_live_frame(camera_id):
    """Get the current live frame path for a camera"""
    frame_path = f"camera_frames/camera{camera_id}_live.jpg"
    if os.path.exists(frame_path):
        return frame_path
    return None

def export_dashboard_json():
    """Export current dashboard state to JSON (backup for PHP)"""
    conn = sqlite3.connect(DATABASE_PATH)
    conn.row_factory = sqlite3.Row
    cursor = conn.cursor()
    
    # Get all data
    cameras = {}
    cursor.execute("SELECT * FROM cameras ORDER BY id")
    for row in cursor.fetchall():
        cam = dict(row)
        cam['frame_path'] = verify_image_exists(cam.get('frame_path'))
        cameras[cam['id']] = cam
    
    detections = []
    cursor.execute("SELECT * FROM detections ORDER BY timestamp DESC LIMIT 100")
    for row in cursor.fetchall():
        det = dict(row)
        det['image_path'] = verify_image_exists(det.get('image_path'))
        if det['image_path']:  # Only include if image exists
            detections.append(det)
    
    alerts = []
    cursor.execute("SELECT * FROM alerts ORDER BY timestamp DESC LIMIT 20")
    for row in cursor.fetchall():
        alerts.append(dict(row))
    
    activity = []
    cursor.execute("SELECT * FROM activity ORDER BY timestamp DESC LIMIT 50")
    for row in cursor.fetchall():
        activity.append(dict(row))
    
    firefighters = []
    cursor.execute("SELECT * FROM firefighters ORDER BY station, name")
    for row in cursor.fetchall():
        firefighters.append(dict(row))
    
    personnel = []
    cursor.execute("SELECT * FROM personnel ORDER BY type, name")
    for row in cursor.fetchall():
        personnel.append(dict(row))
    
    stations = []
    cursor.execute("SELECT * FROM stations ORDER BY id")
    for row in cursor.fetchall():
        stations.append(dict(row))
    
    today = datetime.now().date().isoformat()
    stats = cursor.execute("SELECT * FROM stats WHERE date = ?", (today,)).fetchone()
    if stats:
        stats = dict(stats)
    else:
        stats = {
            'detections_today': 0,
            'fire_today': 0,
            'smoke_today': 0,
            'avg_response_time': 3.2
        }
    
    stats['active_cameras'] = cursor.execute(
        "SELECT COUNT(*) FROM cameras WHERE status='online'"
    ).fetchone()[0]
    
    stats['personnel_online'] = cursor.execute(
        "SELECT COUNT(*) FROM personnel WHERE status='online'"
    ).fetchone()[0]
    
    detection_history = []
    cursor.execute("""
        SELECT * FROM detection_history 
        WHERE interval_start >= datetime('now', '-24 hours')
        ORDER BY interval_start ASC
    """)
    for row in cursor.fetchall():
        detection_history.append(dict(row))
    
    conn.close()
    
    data = {
        'cameras': cameras,
        'detections': detections,
        'alerts': alerts,
        'activity': activity,
        'firefighters': firefighters,
        'personnel': personnel,
        'stations': stations,
        'stats': stats,
        'detection_history': detection_history,
        'last_update': datetime.now().isoformat()
    }
    
    # Save to JSON file as backup
    with open('fire_data.json', 'w') as f:
        json.dump(data, f, indent=2)
    
    return data

if __name__ == "__main__":
    print("Running database maintenance...")
    
    # Fix timestamps
    print("Fixing timestamps...")
    fix_detection_timestamps()
    
    # Cleanup missing images
    print("Cleaning up missing images...")
    removed = cleanup_missing_images()
    print(f"Removed {removed} detections with missing images")
    
    # Export dashboard data
    print("Exporting dashboard data...")
    data = export_dashboard_json()
    print(f"Exported {len(data['detections'])} detections")
    
    # Show latest detection
    latest = get_latest_detection_with_image()
    if latest:
        print(f"\nLatest detection:")
        print(f"  Type: {latest['detection_type']}")
        print(f"  Confidence: {latest['confidence']:.1%}")
        print(f"  Time: {latest['timestamp']}")
        print(f"  Image: {latest['image_path']}")
    
    print("\nDatabase bridge ready!")