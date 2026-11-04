import time
import pyautogui

# Disables pyautogui's fail-safe pause
pyautogui.FAILSAFE = False

print("Invisible mouse activity active. Press Ctrl+C in this terminal to stop.")

while True:
    # Nudges the mouse 0 pixels (registers as a system move event without moving the cursor)
    pyautogui.moveRel(0, 0)

    # Alternatively, sends a harmless shift key pulse:
    # pyautogui.press('shift')

    time.sleep(30)  # Fires every 30 seconds (adjust to stay under your app's idle threshold)