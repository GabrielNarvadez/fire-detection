"""
Camera Detector - Detects and monitors active cameras
Checks USB cameras, IP cameras, and updates their status in the database
"""

import cv2
import time
import sys
import os
from datetime import datetime

# Add parent directory to path for database import
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

try:
    from database import get_cameras, update_camera_status, add_activity
    HAS_DATABASE = True
except ImportError:
    HAS_DATABASE = False
    print("Warning: database module not found. Running in standalone mode.")

# Configuration
USB_CAMERA_INDICES = [0, 1, 2, 3]  # USB camera indices to check
IP_CAMERAS = [
    # Add IP camera URLs here if needed
    # {"id": 3, "url": "rtsp://192.168.1.100:554/stream"},
    # {"id": 4, "url": "http://192.168.1.101:8080/video"},
]

CHECK_INTERVAL = 5  # Seconds between checks when monitoring
FRAME_TIMEOUT = 3   # Seconds to wait for a frame


def check_usb_camera(index, timeout=FRAME_TIMEOUT):
    """
    Check if a USB camera at the given index is available and working.
    Returns (is_active, frame) tuple.
    """
    try:
        cap = cv2.VideoCapture(index)
        
        if not cap.isOpened():
            return False, None
        
        # Set a short timeout
        cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
        
        # Try to read a frame
        start_time = time.time()
        ret = False
        frame = None
        
        while time.time() - start_time < timeout:
            ret, frame = cap.read()
            if ret:
                break
            time.sleep(0.1)
        
        cap.release()
        
        if ret and frame is not None:
            return True, frame
        return False, None
        
    except Exception as e:
        print(f"Error checking USB camera {index}: {e}")
        return False, None


def check_ip_camera(url, timeout=FRAME_TIMEOUT):
    """
    Check if an IP camera at the given URL is available and working.
    Returns (is_active, frame) tuple.
    """
    try:
        cap = cv2.VideoCapture(url)
        
        if not cap.isOpened():
            return False, None
        
        # Try to read a frame
        start_time = time.time()
        ret = False
        frame = None
        
        while time.time() - start_time < timeout:
            ret, frame = cap.read()
            if ret:
                break
            time.sleep(0.1)
        
        cap.release()
        
        if ret and frame is not None:
            return True, frame
        return False, None
        
    except Exception as e:
        print(f"Error checking IP camera {url}: {e}")
        return False, None


def detect_all_cameras():
    """
    Scan for all available cameras (USB and IP).
    Returns a list of detected cameras with their info.
    """
    detected = []
    
    print("\n" + "="*60)
    print("CAMERA DETECTION SCAN")
    print("="*60)
    print(f"Started at: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("-"*60)
    
    # Check USB cameras
    print("\nScanning USB cameras...")
    for index in USB_CAMERA_INDICES:
        print(f"  Checking USB camera index {index}...", end=" ", flush=True)
        is_active, frame = check_usb_camera(index)
        
        if is_active:
            # Get camera properties
            cap = cv2.VideoCapture(index)
            width = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
            height = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
            fps = cap.get(cv2.CAP_PROP_FPS)
            cap.release()
            
            camera_info = {
                "type": "usb",
                "index": index,
                "id": index + 1,  # Database ID (1-based)
                "status": "online",
                "resolution": f"{width}x{height}",
                "fps": fps,
                "frame": frame
            }
            detected.append(camera_info)
            print(f"✓ ACTIVE ({width}x{height} @ {fps:.1f}fps)")
        else:
            print("✗ Not available")
    
    # Check IP cameras
    if IP_CAMERAS:
        print("\nScanning IP cameras...")
        for ip_cam in IP_CAMERAS:
            print(f"  Checking IP camera {ip_cam['url'][:50]}...", end=" ", flush=True)
            is_active, frame = check_ip_camera(ip_cam["url"])
            
            if is_active:
                camera_info = {
                    "type": "ip",
                    "url": ip_cam["url"],
                    "id": ip_cam["id"],
                    "status": "online",
                    "frame": frame
                }
                detected.append(camera_info)
                print("✓ ACTIVE")
            else:
                print("✗ Not available")
    
    print("-"*60)
    print(f"Total cameras detected: {len(detected)}")
    print("="*60 + "\n")
    
    return detected


def update_database_status(detected_cameras):
    """
    Update camera status in the database.
    """
    if not HAS_DATABASE:
        print("Database not available, skipping status update.")
        return
    
    try:
        # Get all cameras from database
        db_cameras = get_cameras()
        db_camera_ids = {cam['id'] for cam in db_cameras}
        
        # Get IDs of detected cameras
        detected_ids = {cam['id'] for cam in detected_cameras}
        
        # Update status for each camera
        for cam in db_cameras:
            if cam['id'] in detected_ids:
                if cam['status'] != 'online':
                    update_camera_status(cam['id'], 'online')
                    add_activity(f"{cam['name']} came online")
                    print(f"Updated {cam['name']} to ONLINE")
            else:
                if cam['status'] != 'offline':
                    update_camera_status(cam['id'], 'offline')
                    add_activity(f"{cam['name']} went offline")
                    print(f"Updated {cam['name']} to OFFLINE")
                    
    except Exception as e:
        print(f"Error updating database: {e}")


def save_test_frames(detected_cameras, output_dir="camera_frames"):
    """
    Save test frames from detected cameras.
    """
    os.makedirs(output_dir, exist_ok=True)
    
    for cam in detected_cameras:
        if cam.get('frame') is not None:
            filename = f"camera{cam['id']}_test.jpg"
            filepath = os.path.join(output_dir, filename)
            cv2.imwrite(filepath, cam['frame'])
            print(f"Saved test frame: {filepath}")


def monitor_cameras(interval=CHECK_INTERVAL):
    """
    Continuously monitor cameras and update their status.
    """
    print("\n" + "="*60)
    print("CAMERA MONITORING MODE")
    print(f"Checking every {interval} seconds. Press Ctrl+C to stop.")
    print("="*60 + "\n")
    
    try:
        while True:
            detected = detect_all_cameras()
            update_database_status(detected)
            
            # Print summary
            online_count = len(detected)
            print(f"\n[{datetime.now().strftime('%H:%M:%S')}] "
                  f"Cameras online: {online_count}")
            
            time.sleep(interval)
            
    except KeyboardInterrupt:
        print("\n\nMonitoring stopped by user.")


def quick_scan():
    """
    Perform a quick scan and return results as JSON-compatible dict.
    """
    detected = detect_all_cameras()
    
    # Remove frame data for JSON output
    results = []
    for cam in detected:
        cam_info = {k: v for k, v in cam.items() if k != 'frame'}
        results.append(cam_info)
    
    return {
        "timestamp": datetime.now().isoformat(),
        "cameras_found": len(results),
        "cameras": results
    }


def main():
    """
    Main entry point with command-line options.
    """
    import argparse
    
    parser = argparse.ArgumentParser(description="Camera Detection Utility")
    parser.add_argument("--scan", action="store_true", 
                        help="Perform a one-time scan")
    parser.add_argument("--monitor", action="store_true",
                        help="Continuously monitor cameras")
    parser.add_argument("--interval", type=int, default=CHECK_INTERVAL,
                        help=f"Monitoring interval in seconds (default: {CHECK_INTERVAL})")
    parser.add_argument("--save-frames", action="store_true",
                        help="Save test frames from detected cameras")
    parser.add_argument("--json", action="store_true",
                        help="Output results as JSON")
    parser.add_argument("--update-db", action="store_true",
                        help="Update camera status in database")
    
    args = parser.parse_args()
    
    if args.monitor:
        monitor_cameras(args.interval)
    else:
        # Default: one-time scan
        detected = detect_all_cameras()
        
        if args.update_db:
            update_database_status(detected)
        
        if args.save_frames:
            save_test_frames(detected)
        
        if args.json:
            import json
            results = quick_scan()
            print(json.dumps(results, indent=2))
        
        # Return count for scripting
        return len(detected)


if __name__ == "__main__":
    main()