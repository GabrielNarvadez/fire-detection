import cv2
from ultralytics import YOLO
import os
import json
from datetime import datetime, timedelta
import threading
import time

# FORCE CPU USAGE (fixes old GPU compatibility issues)
import torch
torch.cuda.is_available = lambda: False

# -------- SETTINGS --------
MODEL_PATH = "10best.pt"
SAVE_DIR_IMG = "detected_images"
DATA_FILE = "fire_data.json"
CAMERA_FRAMES_DIR = "camera_frames"  # New: for live camera feeds

# New: clip saving settings
SAVE_DIR_CLIP = "detected_clips"  # For short detection clips
CLIP_DURATION_SEC = 5             # Duration window for saved clips

# Frame buffers for each camera
FRAME_BUFFERS = {}  # {camera_id: [(timestamp, frame), ...]}

# Detection thresholds
FIRE_CONFIDENCE_THRESHOLD = 0.70
SMOKE_CONFIDENCE_THRESHOLD = 0.65

# Camera Configuration
CAMERAS = {
    1: {
        'name': 'Camera 1 - Visual ML',
        'type': 'visual',
        'location': 'Building A - Warehouse',
        'latitude': 14.6005,
        'longitude': 120.9850,
        'status': 'offline',
        'temperature': 22.0,
        'frame_path': 'camera_frames/camera1_live.jpg'
    },
    2: {
        'name': 'Camera 2 - Thermal',
        'type': 'thermal',
        'location': 'Building A - Warehouse',
        'latitude': 14.6010,
        'longitude': 120.9855,
        'status': 'offline',
        'temperature': 22.5,
        'frame_path': 'camera_frames/camera2_live.jpg'
    }
}

os.makedirs(SAVE_DIR_IMG, exist_ok=True)
os.makedirs(CAMERA_FRAMES_DIR, exist_ok=True)
os.makedirs(SAVE_DIR_CLIP, exist_ok=True)

# Initialize data structure
fire_data = {
    'cameras': CAMERAS,
    'detections': [],
    'alerts': [],
    'activity': [],
    'firefighters': [],  # User-managed firefighters
    'personnel': [
        {'name': 'Admin Johnson', 'role': 'System Administrator', 'type': 'admin', 'status': 'online'},
        {'name': 'Admin Chen', 'role': 'Operations Manager', 'type': 'admin', 'status': 'online'},
        {'name': 'FF Rodriguez', 'role': 'Fire Chief - Station 1', 'type': 'firefighter', 'status': 'online', 'station': 1},
        {'name': 'FF Martinez', 'role': 'Firefighter - Station 1', 'type': 'firefighter', 'status': 'online', 'station': 1},
        {'name': 'FF Santos', 'role': 'Firefighter - Station 1', 'type': 'firefighter', 'status': 'online', 'station': 1},
        {'name': 'FF Reyes', 'role': 'Firefighter - Station 1', 'type': 'firefighter', 'status': 'online', 'station': 1},
        {'name': 'FF Cruz', 'role': 'Firefighter - Station 1', 'type': 'firefighter', 'status': 'online', 'station': 1},
        {'name': 'FF Bautista', 'role': 'Firefighter - Station 1', 'type': 'firefighter', 'status': 'online', 'station': 1},
        {'name': 'FF Garcia', 'role': 'Fire Chief - Station 2', 'type': 'firefighter', 'status': 'online', 'station': 2},
        {'name': 'FF Lopez', 'role': 'Firefighter - Station 2', 'type': 'firefighter', 'status': 'online', 'station': 2},
        {'name': 'FF Hernandez', 'role': 'Firefighter - Station 2', 'type': 'firefighter', 'status': 'online', 'station': 2},
        {'name': 'FF Dela Cruz', 'role': 'Firefighter - Station 2', 'type': 'firefighter', 'status': 'online', 'station': 2},
    ],
    'stations': [
        {'id': 1, 'name': 'Fire Station 1', 'latitude': 14.5950, 'longitude': 120.9800, 'personnel_count': 6},
        {'id': 2, 'name': 'Fire Station 2', 'latitude': 14.6040, 'longitude': 120.9900, 'personnel_count': 6}
    ],
    'stats': {
        'detections_today': 0,
        'fire_today': 0,
        'smoke_today': 0,
        'avg_response_time': 3.2,
        'personnel_online': 12,
        'active_cameras': 0
    },
    'last_update': datetime.now().isoformat()
}

# Load existing data if file exists
def load_existing_data():
    """Load existing fire_data from JSON file to preserve firefighters and other data"""
    global fire_data
    if os.path.exists(DATA_FILE):
        try:
            with open(DATA_FILE, 'r') as f:
                existing_data = json.load(f)
                # Preserve firefighters and other persistent data
                if 'firefighters' in existing_data:
                    fire_data['firefighters'] = existing_data['firefighters']
                # Keep recent detections/alerts/activity
                if 'detections' in existing_data and len(existing_data['detections']) > 0:
                    fire_data['detections'] = existing_data['detections']
                if 'alerts' in existing_data and len(existing_data['alerts']) > 0:
                    fire_data['alerts'] = existing_data['alerts']
                if 'activity' in existing_data and len(existing_data['activity']) > 0:
                    fire_data['activity'] = existing_data['activity']
        except Exception as e:
            print(f"Could not load existing data: {e}")

load_existing_data()

# Load model
print("Loading YOLO model...")
model = YOLO(MODEL_PATH)
print("Model loaded successfully!")

# Save data to JSON file
def save_data():
    """Save fire_data to JSON file"""
    fire_data['last_update'] = datetime.now().isoformat()
    with open(DATA_FILE, 'w') as f:
        json.dump(fire_data, f, indent=2, default=str)

# Add activity log
def add_activity(message):
    """Add activity to log"""
    activity = {
        'timestamp': datetime.now().isoformat(),
        'message': message
    }
    fire_data['activity'].insert(0, activity)
    
    # Keep only last 50 activities
    if len(fire_data['activity']) > 50:
        fire_data['activity'] = fire_data['activity'][:50]
    
    save_data()
    print(f"[ACTIVITY] {message}")

# Update camera status
def update_camera_status(camera_id, status, temperature=None):
    """Update camera status"""
    if camera_id in fire_data['cameras']:
        fire_data['cameras'][camera_id]['status'] = status
        if temperature is not None:
            fire_data['cameras'][camera_id]['temperature'] = temperature
        
        # Update active cameras count
        active = sum(1 for cam in fire_data['cameras'].values() if cam['status'] == 'online')
        fire_data['stats']['active_cameras'] = active
        
        save_data()

# New: frame buffer utilities
def update_frame_buffer(camera_id, frame):
    """Keep a rolling buffer of frames for each camera."""
    now = time.time()
    if camera_id not in FRAME_BUFFERS:
        FRAME_BUFFERS[camera_id] = []
    buf = FRAME_BUFFERS[camera_id]

    # Store a copy to avoid later modification issues
    buf.append((now, frame.copy()))

    # Drop frames older than CLIP_DURATION_SEC
    cutoff = now - CLIP_DURATION_SEC
    while buf and buf[0][0] < cutoff:
        buf.pop(0)

def save_detection_clip(camera_id, detection_id):
    """Save a short mp4 clip from the frame buffer and attach it to the detection."""
    buf = FRAME_BUFFERS.get(camera_id, [])
    if not buf:
        return None

    frames = [f for (_, f) in buf]
    if not frames:
        return None

    height, width, _ = frames[0].shape

    # Simple fps estimate
    fps = len(frames) / CLIP_DURATION_SEC if len(frames) > 1 else 10

    filename = f"camera{camera_id}_det_{detection_id}.mp4"
    save_path = os.path.join(SAVE_DIR_CLIP, filename)

    fourcc = cv2.VideoWriter_fourcc(*"mp4v")
    out = cv2.VideoWriter(save_path, fourcc, fps, (width, height))

    for fr in frames:
        out.write(fr)
    out.release()

    # Attach clip path to detection
    for d in fire_data["detections"]:
        if d["id"] == detection_id:
            d["clip_path"] = save_path
            break

    save_data()
    print(f"💾 Saved detection clip: {save_path}")
    return save_path

# Log detection
def log_detection(camera_id, detection_type, confidence, image_path):
    """Log a fire/smoke detection"""
    detection = {
        'id': len(fire_data['detections']) + 1,
        'camera_id': camera_id,
        'camera_name': fire_data['cameras'][camera_id]['name'],
        'detection_type': detection_type,
        'confidence': confidence,
        'image_path': image_path,
        'location': fire_data['cameras'][camera_id]['location'],
        'latitude': fire_data['cameras'][camera_id]['latitude'],
        'longitude': fire_data['cameras'][camera_id]['longitude'],
        'status': 'pending',
        'timestamp': datetime.now().isoformat()
    }
    
    fire_data['detections'].insert(0, detection)
    
    # Keep only last 100 detections
    if len(fire_data['detections']) > 100:
        fire_data['detections'] = fire_data['detections'][:100]
    
    # Update today's stats
    today = datetime.now().date()
    fire_data['stats']['detections_today'] = sum(
        1 for d in fire_data['detections'] 
        if datetime.fromisoformat(d['timestamp']).date() == today
    )
    fire_data['stats']['fire_today'] = sum(
        1 for d in fire_data['detections'] 
        if d['detection_type'] == 'fire' and datetime.fromisoformat(d['timestamp']).date() == today
    )
    fire_data['stats']['smoke_today'] = sum(
        1 for d in fire_data['detections'] 
        if d['detection_type'] == 'smoke' and datetime.fromisoformat(d['timestamp']).date() == today
    )
    
    # Create alert if high confidence
    if confidence >= 0.85:
        create_alert(detection['id'], detection_type, confidence, detection['location'])
    
    save_data()
    return detection['id']

# Create alert
def create_alert(detection_id, detection_type, confidence, location):
    """Create a critical alert"""
    alert_level = 'critical' if detection_type == 'fire' else 'warning'
    
    alert = {
        'id': len(fire_data['alerts']) + 1,
        'detection_id': detection_id,
        'alert_level': alert_level,
        'message': f"{detection_type.upper()} detected at {location} - Confidence: {confidence:.1%}",
        'status': 'active',
        'timestamp': datetime.now().isoformat()
    }
    
    fire_data['alerts'].insert(0, alert)
    
    # Keep only last 20 alerts
    if len(fire_data['alerts']) > 20:
        fire_data['alerts'] = fire_data['alerts'][:20]
    
    add_activity(f"🚨 ALERT: {alert['message']}")
    save_data()

# Process detection results
def process_detection_results(results, camera_id, frame, save_image=True):
    """Process YOLO detection results"""
    detection_info = {
        'has_fire': False,
        'has_smoke': False,
        'max_fire_confidence': 0,
        'max_smoke_confidence': 0
    }
    
    # Parse YOLO results
    for result in results:
        boxes = result.boxes
        for box in boxes:
            cls_id = int(box.cls[0])
            confidence = float(box.conf[0])
            class_name = result.names[cls_id].lower()
            
            if 'fire' in class_name:
                detection_info['has_fire'] = True
                detection_info['max_fire_confidence'] = max(
                    detection_info['max_fire_confidence'], 
                    confidence
                )
            elif 'smoke' in class_name:
                detection_info['has_smoke'] = True
                detection_info['max_smoke_confidence'] = max(
                    detection_info['max_smoke_confidence'], 
                    confidence
                )
    
    # Save and log if detection found
    if save_image and (detection_info['has_fire'] or detection_info['has_smoke']):
        if detection_info['max_fire_confidence'] >= detection_info['max_smoke_confidence']:
            log_type = 'fire'
            confidence = detection_info['max_fire_confidence']
        else:
            log_type = 'smoke'
            confidence = detection_info['max_smoke_confidence']
        
        threshold = FIRE_CONFIDENCE_THRESHOLD if log_type == 'fire' else SMOKE_CONFIDENCE_THRESHOLD
        
        if confidence >= threshold:
            # Save annotated image
            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            save_name = f"camera{camera_id}_{log_type}_{timestamp}.jpg"
            save_path = os.path.join(SAVE_DIR_IMG, save_name)
            
            annotated_frame = results[0].plot()
            cv2.imwrite(save_path, annotated_frame)
            
            # Log detection
            detection_id = log_detection(camera_id, log_type, confidence, save_path)
            detection_info['detection_id'] = detection_id

            # Save short clip from buffer
            clip_path = save_detection_clip(camera_id, detection_id)
            if clip_path:
                detection_info['clip_path'] = clip_path
            
            print(f"\n🚨 {log_type.upper()} DETECTED! Confidence: {confidence:.1%}")
    
    return detection_info

# Webcam detection mode
def detect_from_webcam(camera_id=1):
    """Run detection on webcam"""
    cap = cv2.VideoCapture(0)
    
    if not cap.isOpened():
        print("❌ Error: Could not open webcam.")
        return
    
    print(f"\n{'='*60}")
    print(f"🎥 {fire_data['cameras'][camera_id]['name']}")
    print(f"{'='*60}")
    print("Press 'q' to quit, 's' to save current frame")
    print("📺 Camera feed is also streaming to dashboard!")
    print("   View at: http://localhost:8000")
    print(f"{'='*60}\n")
    
    update_camera_status(camera_id, 'online')
    add_activity(f"{fire_data['cameras'][camera_id]['name']} started")
    
    frame_count = 0
    detection_cooldown = 0
    last_frame_save = 0
    
    try:
        while True:
            ret, frame = cap.read()
            if not ret:
                break

            # Update frame buffer for clips
            update_frame_buffer(camera_id, frame)
            
            frame_count += 1
            
            # Save frame for dashboard every 10 frames (about 2 times per second)
            if frame_count - last_frame_save >= 10:
                frame_path = os.path.join(CAMERA_FRAMES_DIR, f"camera{camera_id}_live.jpg")
                cv2.imwrite(frame_path, frame)
                last_frame_save = frame_count
            
            # Run detection every 5 frames
            if frame_count % 5 == 0:
                results = model.predict(source=frame, verbose=False)
                annotated_frame = results[0].plot()
                
                # Process detections
                should_save = (detection_cooldown <= 0)
                detection_info = process_detection_results(results, camera_id, frame, save_image=should_save)
                
                if detection_info.get('detection_id'):
                    detection_cooldown = 300  # 30 seconds cooldown
                
                if detection_cooldown > 0:
                    detection_cooldown -= 1
                
                # Update temperature (simulated for thermal camera)
                if camera_id == 2:
                    temp = 22 + (detection_info['max_fire_confidence'] * 100)
                    update_camera_status(camera_id, 'online', temperature=temp)
            else:
                annotated_frame = frame
            
            # Display
            cv2.imshow("Fire & Smoke Detection", annotated_frame)
            
            key = cv2.waitKey(1) & 0xFF
            if key == ord('q'):
                break
            elif key == ord('s'):
                timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
                save_name = f"camera{camera_id}_manual_{timestamp}.jpg"
                save_path = os.path.join(SAVE_DIR_IMG, save_name)
                cv2.imwrite(save_path, annotated_frame)
                print(f"💾 Saved: {save_name}")
    
    finally:
        update_camera_status(camera_id, 'offline')
        add_activity(f"{fire_data['cameras'][camera_id]['name']} stopped")
        cap.release()
        cv2.destroyAllWindows()

# Dual camera mode
def detect_dual_cameras():
    """Run detection on two cameras simultaneously"""
    print(f"\n{'='*60}")
    print("🎥 DUAL CAMERA MODE")
    print(f"{'='*60}")
    
    cap1 = cv2.VideoCapture(0)
    cap2 = cv2.VideoCapture(1)
    
    if not cap1.isOpened():
        print("❌ Error: Could not open camera 1")
        return
    
    update_camera_status(1, 'online')
    add_activity('Dual camera monitoring started')
    
    has_camera2 = cap2.isOpened()
    if has_camera2:
        update_camera_status(2, 'online')
    else:
        print("⚠️  Camera 2 not available, using single camera mode")
    
    print("Press 'q' to quit")
    print("📺 Camera feeds are streaming to dashboard!")
    print("   View at: http://localhost:8000")
    print(f"{'='*60}\n")
    
    frame_count = 0
    detection_cooldown_1 = 0
    detection_cooldown_2 = 0
    last_frame_save = 0
    
    try:
        while True:
            ret1, frame1 = cap1.read()
            if not ret1:
                break
            
            frame_count += 1

            # Update buffer for camera 1
            update_frame_buffer(1, frame1)
            
            # Save frames for dashboard every 10 frames
            if frame_count - last_frame_save >= 10:
                frame1_path = os.path.join(CAMERA_FRAMES_DIR, "camera1_live.jpg")
                cv2.imwrite(frame1_path, frame1)
                last_frame_save = frame_count
            
            # Process camera 1
            if frame_count % 5 == 0:
                results1 = model.predict(source=frame1, verbose=False)
                annotated_frame1 = results1[0].plot()
                
                should_save_1 = (detection_cooldown_1 <= 0)
                detection_info_1 = process_detection_results(results1, 1, frame1, save_image=should_save_1)
                
                if detection_info_1.get('detection_id'):
                    detection_cooldown_1 = 300
                if detection_cooldown_1 > 0:
                    detection_cooldown_1 -= 1
            else:
                annotated_frame1 = frame1
            
            # Process camera 2
            if has_camera2:
                ret2, frame2 = cap2.read()
                if ret2:
                    # Update buffer for camera 2
                    update_frame_buffer(2, frame2)

                    # Save camera 2 frame (note: shares last_frame_save)
                    if frame_count - last_frame_save >= 10:
                        frame2_path = os.path.join(CAMERA_FRAMES_DIR, "camera2_live.jpg")
                        cv2.imwrite(frame2_path, frame2)
                    
                    if frame_count % 5 == 0:
                        results2 = model.predict(source=frame2, verbose=False)
                        annotated_frame2 = results2[0].plot()
                        
                        should_save_2 = (detection_cooldown_2 <= 0)
                        detection_info_2 = process_detection_results(results2, 2, frame2, save_image=should_save_2)
                        
                        if detection_info_2.get('detection_id'):
                            detection_cooldown_2 = 300
                        if detection_cooldown_2 > 0:
                            detection_cooldown_2 -= 1
                        
                        # Update thermal temperature
                        temp = 22 + (detection_info_2.get('max_fire_confidence', 0) * 100)
                        update_camera_status(2, 'online', temperature=temp)
                    else:
                        annotated_frame2 = frame2
                    
                    # Display side by side
                    combined = cv2.hconcat([annotated_frame1, annotated_frame2])
                    cv2.imshow("Dual Camera Detection", combined)
                else:
                    cv2.imshow("Camera 1 - Visual ML", annotated_frame1)
            else:
                cv2.imshow("Camera 1 - Visual ML", annotated_frame1)
            
            if cv2.waitKey(1) & 0xFF == ord('q'):
                break
    
    finally:
        update_camera_status(1, 'offline')
        if has_camera2:
            update_camera_status(2, 'offline')
            cap2.release()
        add_activity('Dual camera monitoring stopped')
        cap1.release()
        cv2.destroyAllWindows()

# Main menu
def main():
    # Initialize
    add_activity('Fire detection system started')
    save_data()
    
    while True:
        print("\n" + "="*60)
        print("🔥 FIRE & SMOKE DETECTION SYSTEM")
        print("="*60)
        print("1. Dual camera detection (Camera 1 & 2)")
        print("2. Single webcam (Camera 1)")
        print("3. View statistics")
        print("4. Exit")
        print("="*60)
        choice = input("Select option (1-4): ").strip()
        
        if choice == "1":
            detect_dual_cameras()
        elif choice == "2":
            detect_from_webcam(camera_id=1)
        elif choice == "3":
            print(f"\n📊 Today's Statistics:")
            print(f"   Fire detections: {fire_data['stats']['fire_today']}")
            print(f"   Smoke detections: {fire_data['stats']['smoke_today']}")
            print(f"   Total detections: {fire_data['stats']['detections_today']}")
            print(f"   Active cameras: {fire_data['stats']['active_cameras']}")
            print(f"   Personnel online: {fire_data['stats']['personnel_online']}")
        elif choice == "4":
            add_activity('Fire detection system stopped')
            print("👋 Exiting...")
            break
        else:
            print("❌ Invalid choice.")

if __name__ == "__main__":
    main()
