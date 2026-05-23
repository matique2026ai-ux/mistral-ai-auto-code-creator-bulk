import express from 'express';
import MenuController from '../controllers/MenuController';
import { authenticate } from '../middlewares/authMiddleware';
import { authorize } from '../middlewares/authMiddleware';
import { validateRequest } from '../middlewares/validationMiddleware';
import { menuItemSchema } from '../validations/menuValidation';

const router = express.Router();

// Public routes
router.get('/', MenuController.getMenuItems);
router.get('/:id', MenuController.getMenuItemById);

// Admin routes
router.use(authenticate);
router.use(authorize(['admin']));
router.post('/', validateRequest(menuItemSchema), MenuController.createMenuItem);
router.put('/:id', validateRequest(menuItemSchema), MenuController.updateMenuItem);
router.delete('/:id', MenuController.deleteMenuItem);

export default router;