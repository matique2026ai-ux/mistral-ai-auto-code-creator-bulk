import express from 'express';
import AuthController from '../controllers/AuthController';
import { validateRequest } from '../middlewares/validationMiddleware';
import { loginSchema, registerSchema } from '../validations/authValidation';

const router = express.Router();

router.post('/login', validateRequest(loginSchema), AuthController.login);
router.post('/register', validateRequest(registerSchema), AuthController.register);

export default router;