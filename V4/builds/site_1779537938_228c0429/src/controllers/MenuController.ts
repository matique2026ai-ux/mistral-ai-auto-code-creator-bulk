import { Request, Response } from 'express';
import { MenuItem } from '../models/MenuItem';
import logger from '../utils/logger';

class MenuController {
  async getMenuItems(req: Request, res: Response) {
    try {
      const { category } = req.query;
      
      const where: any = {};
      if (category) {
        where.category = category;
      }
      
      const menuItems = await MenuItem.findAll({
        where,
        order: [['category', 'ASC'], ['created_at', 'ASC']]
      });
      
      res.json(menuItems);
    } catch (error) {
      logger.error(`Error fetching menu items: ${error}`);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
  
  async getMenuItemById(req: Request, res: Response) {
    try {
      const { id } = req.params;
      
      const menuItem = await MenuItem.findByPk(id);
      
      if (!menuItem) {
        return res.status(404).json({ error: 'Menu item not found' });
      }
      
      res.json(menuItem);
    } catch (error) {
      logger.error(`Error fetching menu item: ${error}`);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
  
  async createMenuItem(req: Request, res: Response) {
    try {
      const { name, description, price, category, image_url } = req.body;
      
      // Validation
      if (!name || !price || !category) {
        return res.status(400).json({ error: 'Name, price, and category are required' });
      }
      
      const menuItem = await MenuItem.create({
        name,
        description,
        price,
        category,
        image_url
      });
      
      logger.info(`New menu item created: ${name}`);
      res.status(201).json(menuItem);
    } catch (error) {
      logger.error(`Error creating menu item: ${error}`);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
  
  async updateMenuItem(req: Request, res: Response) {
    try {
      const { id } = req.params;
      const { name, description, price, category, image_url } = req.body;
      
      const menuItem = await MenuItem.findByPk(id);
      
      if (!menuItem) {
        return res.status(404).json({ error: 'Menu item not found' });
      }
      
      await menuItem.update({
        name,
        description,
        price,
        category,
        image_url
      });
      
      logger.info(`Menu item updated: ${id}`);
      res.json(menuItem);
    } catch (error) {
      logger.error(`Error updating menu item: ${error}`);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
  
  async deleteMenuItem(req: Request, res: Response) {
    try {
      const { id } = req.params;
      
      const menuItem = await MenuItem.findByPk(id);
      
      if (!menuItem) {
        return res.status(404).json({ error: 'Menu item not found' });
      }
      
      await menuItem.destroy();
      
      logger.info(`Menu item deleted: ${id}`);
      res.json({ message: 'Menu item deleted successfully' });
    } catch (error) {
      logger.error(`Error deleting menu item: ${error}`);
      res.status(500).json({ error: 'Internal server error' });
    }
  }
}

export default new MenuController();