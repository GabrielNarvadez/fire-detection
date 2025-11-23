import cv2
from ultralytics import YOLO
import os
from datetime import datetime

# -------- SETTINGS --------
MODEL_PATH = "10best.pt"               # your YOLO model
SAVE_DIR_IMG = "detected_images"     # for annotated images
SAVE_DIR_VID = "detected_videos"     # for annotated videos

os.makedirs(SAVE_DIR_IMG, exist_ok=True)
os.makedirs(SAVE_DIR_VID, exist_ok=True)

# -------- LOAD MODEL --------
model = YOLO(MODEL_PATH)

# -------- IMAGE MODE: type filename, detect, save --------
def detect_on_image_path():
    image_path = input("Enter image file path (e.g. C:/path/to/image.jpg): ").strip().strip('"')

    if not os.path.isfile(image_path):
        print("File not found. Please check the path.")
        return

    img = cv2.imread(image_path)
    if img is None:
        print("Could not read the image. Unsupported or corrupted file.")
        return

    # Run YOLO detection
    results = model.predict(source=img, verbose=False)
    annotated_img = results[0].plot()

    # Show the detection
    cv2.imshow("Image Detection Result", annotated_img)

    # Build a save filename
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    base_name = os.path.basename(image_path)
    name, ext = os.path.splitext(base_name)
    save_name = f"{name}_detected_{timestamp}{ext}"
    save_path = os.path.join(SAVE_DIR_IMG, save_name)

    # Save annotated image
    cv2.imwrite(save_path, annotated_img)
    print(f"Saved annotated image to: {save_path}")

    print("Press any key on the image window to close it.")
    cv2.waitKey(0)
    cv2.destroyAllWindows()


# -------- WEBCAM MODE --------
def detect_from_webcam():
    cap = cv2.VideoCapture(0)

    if not cap.isOpened():
        print("Error: Could not open webcam.")
        return

    print("Webcam mode: Press 'q' to quit.")

    while True:
        ret, frame = cap.read()
        if not ret:
            print("Failed to grab frame.")
            break

        # Run detection
        results = model.predict(source=frame, verbose=False)
        annotated_frame = results[0].plot()

        cv2.imshow("Fire & Smoke Detection (Webcam)", annotated_frame)

        if cv2.waitKey(1) & 0xFF == ord('q'):
            break

    cap.release()
    cv2.destroyAllWindows()


# -------- VIDEO FILE MODE: detect on video, save annotated video --------
def detect_on_video_file():
    video_path = input("Enter video file path (e.g. C:/path/to/video.mp4): ").strip().strip('"')

    if not os.path.isfile(video_path):
        print("File not found. Please check the path.")
        return

    cap = cv2.VideoCapture(video_path)
    if not cap.isOpened():
        print("Error: Could not open video file.")
        return

    # Get video properties
    fps = cap.get(cv2.CAP_PROP_FPS)
    if fps == 0:
        fps = 20  # fallback

    width = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
    height = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
    frame_size = (width, height)

    # Build save video path
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    base_name = os.path.basename(video_path)
    name, _ = os.path.splitext(base_name)
    out_name = f"{name}_detected_{timestamp}.avi"
    save_path = os.path.join(SAVE_DIR_VID, out_name)

    fourcc = cv2.VideoWriter_fourcc(*'XVID')
    out = cv2.VideoWriter(save_path, fourcc, fps, frame_size)

    if not out.isOpened():
        print("Error: Could not open VideoWriter.")
        cap.release()
        return

    print("Processing video... Press 'q' to stop preview early (video will still save up to that point).")

    while True:
        ret, frame = cap.read()
        if not ret:
            print("End of video or failed to read frame.")
            break

        # Run detection
        results = model.predict(source=frame, verbose=False)
        annotated_frame = results[0].plot()

        # Show preview
        cv2.imshow("Video Detection Result", annotated_frame)

        # Write annotated frame to output video
        out.write(annotated_frame)

        if cv2.waitKey(1) & 0xFF == ord('q'):
            print("Stopped preview by user.")
            break

    cap.release()
    out.release()
    cv2.destroyAllWindows()

    print(f"Saved annotated video to: {save_path}")


# -------- MAIN MENU --------
def main():
    while True:
        print("\n=== Fire & Smoke Detection ===")
        print("1. Webcam detection")
        print("2. Detect on image file (type path)")
        print("3. Detect on video file (type path)")
        print("4. Exit")
        choice = input("Select option (1/2/3/4): ").strip()

        if choice == "1":
            detect_from_webcam()
        elif choice == "2":
            detect_on_image_path()
        elif choice == "3":
            detect_on_video_file()
        elif choice == "4":
            print("Exiting...")
            break
        else:
            print("Invalid choice. Please select 1, 2, 3, or 4.")

if __name__ == "__main__":
    main()
