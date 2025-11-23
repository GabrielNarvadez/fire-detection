#!/usr/bin/env python3
"""
Process uploaded images/videos for fire detection
Called by dashboard.php when user uploads a file
"""

import sys
import cv2
from ultralytics import YOLO
import json
import os

# Force CPU usage
import torch
torch.cuda.is_available = lambda: False

MODEL_PATH = "10best.pt"
ANNOTATED_DIR = "annotated"

def process_image(filepath):
    """Process a single image"""
    try:
        # Load model
        model = YOLO(MODEL_PATH)
        
        # Read image
        img = cv2.imread(filepath)
        if img is None:
            return {'success': False, 'error': 'Could not read image'}
        
        # Run detection
        results = model.predict(source=img, verbose=False)
        
        # Count detections
        fire_count = 0
        smoke_count = 0
        
        for result in results:
            boxes = result.boxes
            for box in boxes:
                cls_id = int(box.cls[0])
                class_name = result.names[cls_id].lower()
                
                if 'fire' in class_name:
                    fire_count += 1
                elif 'smoke' in class_name:
                    smoke_count += 1
        
        # Save annotated image
        annotated_img = results[0].plot()
        filename = os.path.basename(filepath)
        name, ext = os.path.splitext(filename)
        output_filename = f"{name}_annotated{ext}"
        output_path = os.path.join(ANNOTATED_DIR, output_filename)
        
        cv2.imwrite(output_path, annotated_img)
        
        return {
            'success': True,
            'annotated_image': output_path,
            'fire_count': fire_count,
            'smoke_count': smoke_count
        }
        
    except Exception as e:
        return {'success': False, 'error': str(e)}

def process_video(filepath):
    """Process a video file"""
    try:
        # Load model
        model = YOLO(MODEL_PATH)
        
        cap = cv2.VideoCapture(filepath)
        if not cap.isOpened():
            return {'success': False, 'error': 'Could not open video'}
        
        # Get video properties
        fps = int(cap.get(cv2.CAP_PROP_FPS)) or 20
        width = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
        height = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
        
        # Setup output video
        filename = os.path.basename(filepath)
        name, _ = os.path.splitext(filename)
        output_filename = f"{name}_annotated.avi"
        output_path = os.path.join(ANNOTATED_DIR, output_filename)
        
        fourcc = cv2.VideoWriter_fourcc(*'XVID')
        out = cv2.VideoWriter(output_path, fourcc, fps, (width, height))
        
        fire_count = 0
        smoke_count = 0
        frame_count = 0
        
        while True:
            ret, frame = cap.read()
            if not ret:
                break
            
            frame_count += 1
            
            # Process every 5th frame for speed
            if frame_count % 5 == 0:
                results = model.predict(source=frame, verbose=False)
                annotated_frame = results[0].plot()
                
                # Count detections
                for result in results:
                    boxes = result.boxes
                    for box in boxes:
                        cls_id = int(box.cls[0])
                        class_name = result.names[cls_id].lower()
                        
                        if 'fire' in class_name:
                            fire_count += 1
                        elif 'smoke' in class_name:
                            smoke_count += 1
            else:
                annotated_frame = frame
            
            out.write(annotated_frame)
        
        cap.release()
        out.release()
        
        return {
            'success': True,
            'annotated_video': output_path,
            'fire_count': fire_count,
            'smoke_count': smoke_count
        }
        
    except Exception as e:
        return {'success': False, 'error': str(e)}

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(json.dumps({'success': False, 'error': 'Missing arguments'}))
        sys.exit(1)
    
    filepath = sys.argv[1]
    file_type = sys.argv[2]
    
    os.makedirs(ANNOTATED_DIR, exist_ok=True)
    
    if file_type == 'image':
        result = process_image(filepath)
    elif file_type == 'video':
        result = process_video(filepath)
    else:
        result = {'success': False, 'error': 'Invalid file type'}
    
    print(json.dumps(result))